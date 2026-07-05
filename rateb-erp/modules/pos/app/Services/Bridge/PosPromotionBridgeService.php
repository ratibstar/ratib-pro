<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Promotion engine bridge — reads rateb_pos_promotions only. */
final class PosPromotionBridgeService
{
    /**
     * @param array<string, mixed> $context branch_id, customer_id, quantity
     * @return array{price: float, promotion_id: int, discount_amount: float}|null
     */
    public function resolveActivePromotion(int $inventoryId, int $companyId, array $context = []): ?array
    {
        if ($inventoryId < 1 || $companyId < 1 || !$this->tableExists('rateb_pos_promotions')) {
            return null;
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, code, name, rules_json FROM rateb_pos_promotions
             WHERE company_id = :cid AND is_active = 1
               AND (valid_from IS NULL OR valid_from <= NOW())
               AND (valid_to IS NULL OR valid_to >= NOW())
             ORDER BY id DESC'
        );
        $stmt->execute(['cid' => $companyId]);
        $base = (new PosPricingBridgeService())->inventoryPriceBase($inventoryId, $companyId);
        $listPrice = (float) ($base['price'] ?? 0);
        if ($listPrice <= 0) {
            return null;
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $rules = $this->decodeRules($row['rules_json'] ?? null);
            if (!$this->rulesMatchItem($rules, $inventoryId, $context)) {
                continue;
            }
            $promoPrice = $this->applyRules($listPrice, $rules, (float) ($context['quantity'] ?? 1));
            if ($promoPrice <= 0 || $promoPrice >= $listPrice) {
                continue;
            }
            return [
                'price' => round($promoPrice, 2),
                'promotion_id' => (int) ($row['id'] ?? 0),
                'discount_amount' => round($listPrice - $promoPrice, 2),
            ];
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function decodeRules(mixed $json): array
    {
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $rules @param array<string, mixed> $context */
    private function rulesMatchItem(array $rules, int $inventoryId, array $context): bool
    {
        $ids = $rules['inventory_ids'] ?? $rules['product_ids'] ?? null;
        if (is_array($ids) && $ids !== []) {
            return in_array($inventoryId, array_map('intval', $ids), true);
        }
        $branchId = (int) ($context['branch_id'] ?? 0);
        $ruleBranch = (int) ($rules['branch_id'] ?? 0);
        if ($ruleBranch > 0 && $branchId > 0 && $ruleBranch !== $branchId) {
            return false;
        }
        return true;
    }

    /** @param array<string, mixed> $rules */
    private function applyRules(float $listPrice, array $rules, float $quantity): float
    {
        $type = (string) ($rules['type'] ?? 'percent_off');
        $value = (float) ($rules['value'] ?? 0);
        if ($type === 'fixed_price') {
            return max(0, $value);
        }
        if ($type === 'fixed_off') {
            return max(0, $listPrice - $value);
        }
        if ($value > 0 && $value <= 100) {
            return max(0, round($listPrice * (1 - ($value / 100)), 2));
        }
        unset($quantity);
        return $listPrice;
    }

    public function recordApplicationInTransaction(
        int $companyId,
        int $promotionId,
        int $orderId,
        ?int $inventoryId,
        float $discountAmount
    ): void {
        if ($promotionId < 1 || $orderId < 1 || !$this->tableExists('rateb_pos_promotion_applications')) {
            return;
        }
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        Database::connection()->prepare(
            'INSERT INTO rateb_pos_promotion_applications
             (company_id, promotion_id, order_id, inventory_id, discount_amount)
             VALUES (:cid, :pid, :oid, :iid, :amt)'
        )->execute([
            'cid' => $companyId,
            'pid' => $promotionId,
            'oid' => $orderId,
            'iid' => $inventoryId,
            'amt' => round($discountAmount, 2),
        ]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
