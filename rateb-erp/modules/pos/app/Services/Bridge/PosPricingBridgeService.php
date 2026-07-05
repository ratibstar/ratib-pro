<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Read-only ERP pricing data for POS (no business logic duplication). */
final class PosPricingBridgeService
{
    /** @return array{price: float, sell_price: ?float, unit_cost: float} */
    public function inventoryPriceBase(int $inventoryId, int $companyId): array
    {
        if ($inventoryId < 1 || $companyId < 1) {
            return ['price' => 0.0, 'sell_price' => null, 'unit_cost' => 0.0];
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT sell_price, unit_cost FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['price' => 0.0, 'sell_price' => null, 'unit_cost' => 0.0];
        }
        $sell = isset($row['sell_price']) && $row['sell_price'] !== null
            ? (float) $row['sell_price']
            : null;
        $cost = (float) ($row['unit_cost'] ?? 0);
        $default = $sell !== null && $sell > 0 ? $sell : $cost;
        return ['price' => round($default, 2), 'sell_price' => $sell, 'unit_cost' => $cost];
    }

    public function branchPrice(int $inventoryId, int $branchId, int $companyId): ?float
    {
        if ($inventoryId < 1 || $branchId < 1 || $companyId < 1 || !$this->tableExists('rateb_pos_branch_prices')) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT price FROM rateb_pos_branch_prices
             WHERE company_id = :cid AND branch_id = :bid AND inventory_id = :iid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'bid' => $branchId, 'iid' => $inventoryId]);
        $price = $stmt->fetchColumn();
        return $price !== false && (float) $price > 0 ? round((float) $price, 2) : null;
    }

    public function groupPrice(int $inventoryId, int $priceGroupId, int $companyId): ?float
    {
        if ($inventoryId < 1 || $priceGroupId < 1 || $companyId < 1 || !$this->tableExists('rateb_pos_group_prices')) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT price FROM rateb_pos_group_prices
             WHERE company_id = :cid AND price_group_id = :gid AND inventory_id = :iid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'gid' => $priceGroupId, 'iid' => $inventoryId]);
        $price = $stmt->fetchColumn();
        return $price !== false && (float) $price > 0 ? round((float) $price, 2) : null;
    }

    /**
     * Future promotion hook — returns null until promotion engine is implemented.
     *
     * @param array<string, mixed> $context
     * @return array{price: float, promotion_id: ?int}|null
     */
    public function resolveActivePromotion(int $inventoryId, int $companyId, array $context = []): ?array
    {
        return (new PosPromotionBridgeService())->resolveActivePromotion($inventoryId, $companyId, $context);
    }

    private function tableExists(string $table): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
