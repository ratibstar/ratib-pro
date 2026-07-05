<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Coupon validation and redemption bridge. */
final class PosCouponBridgeService
{
    /** @return array{ok: bool, error?: string, coupon_id?: int, discount_type?: string, discount_value?: float} */
    public function validate(string $code, int $companyId, float $subtotal): array
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $companyId < 1) {
            return ['ok' => false, 'error' => __('invalid_request')];
        }
        $row = $this->findCoupon($code, $companyId);
        if ($row === null) {
            return ['ok' => false, 'error' => __('pos_coupon_invalid')];
        }
        if ((string) ($row['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => __('pos_coupon_inactive')];
        }
        $today = date('Y-m-d');
        $from = (string) ($row['valid_from'] ?? '');
        $to = (string) ($row['valid_to'] ?? '');
        if ($from !== '' && $from > $today) {
            return ['ok' => false, 'error' => __('pos_coupon_not_yet_valid')];
        }
        if ($to !== '' && $to < $today) {
            return ['ok' => false, 'error' => __('pos_coupon_expired')];
        }
        $maxUses = isset($row['max_uses']) ? (int) $row['max_uses'] : 0;
        $used = (int) ($row['used_count'] ?? 0);
        if ($maxUses > 0 && $used >= $maxUses) {
            return ['ok' => false, 'error' => __('pos_coupon_exhausted')];
        }
        if ($subtotal <= 0) {
            return ['ok' => false, 'error' => __('pos_coupon_subtotal_required')];
        }
        return [
            'ok' => true,
            'coupon_id' => (int) ($row['id'] ?? 0),
            'discount_type' => (string) ($row['discount_type'] ?? 'percent'),
            'discount_value' => (float) ($row['discount_value'] ?? 0),
        ];
    }

    public function discountAmount(string $discountType, float $discountValue, float $subtotal): float
    {
        if ($subtotal <= 0 || $discountValue <= 0) {
            return 0.0;
        }
        if ($discountType === 'fixed') {
            return min($subtotal, round($discountValue, 2));
        }
        return min($subtotal, round($subtotal * min(100, $discountValue) / 100, 2));
    }

    public function redeemInTransaction(
        int $couponId,
        int $orderId,
        int $companyId,
        int $branchId,
        float $discountAmount
    ): void {
        if ($couponId < 1 || $orderId < 1 || !Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, used_count, max_uses FROM rateb_pos_coupons
             WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $couponId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('pos_coupon_invalid'));
        }
        $maxUses = isset($row['max_uses']) ? (int) $row['max_uses'] : 0;
        $used = (int) ($row['used_count'] ?? 0);
        if ($maxUses > 0 && $used >= $maxUses) {
            throw new \RuntimeException(__('pos_coupon_exhausted'));
        }
        $db->prepare('UPDATE rateb_pos_coupons SET used_count = used_count + 1 WHERE id = :id')
            ->execute(['id' => $couponId]);
        if ($this->tableExists('rateb_pos_coupon_redemptions')) {
            $db->prepare(
                'INSERT INTO rateb_pos_coupon_redemptions (company_id, branch_id, coupon_id, order_id, discount_amount)
                 VALUES (:cid, :bid, :cpid, :oid, :amt)'
            )->execute([
                'cid' => $companyId,
                'bid' => $branchId > 0 ? $branchId : null,
                'cpid' => $couponId,
                'oid' => $orderId,
                'amt' => round($discountAmount, 2),
            ]);
        }
    }

    /**
     * Reverse coupon redemption when policy and coupon rules allow.
     *
     * @return array{reversed: bool, redemption_id?: int, amount?: float}
     */
    public function reverseRedemptionInTransaction(
        int $couponId,
        int $originalOrderId,
        int $returnOrderId,
        int $companyId,
        bool $fullOrderReturn,
        bool $fullOnlyPolicy
    ): array {
        if ($couponId < 1 || $originalOrderId < 1 || !Database::connection()->inTransaction()) {
            return ['reversed' => false];
        }
        if ($fullOnlyPolicy && !$fullOrderReturn) {
            return ['reversed' => false];
        }
        if (!$this->tableExists('rateb_pos_coupon_redemptions')) {
            return ['reversed' => false];
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT c.id, c.reversible, c.used_count, r.id AS redemption_id, r.discount_amount, r.reversed_at
             FROM rateb_pos_coupons c
             INNER JOIN rateb_pos_coupon_redemptions r ON r.coupon_id = c.id AND r.order_id = :oid
             WHERE c.id = :cpid AND c.company_id = :cid
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['cpid' => $couponId, 'oid' => $originalOrderId, 'cid' => $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (int) ($row['reversible'] ?? 1) !== 1) {
            return ['reversed' => false];
        }
        if (!empty($row['reversed_at'])) {
            return ['reversed' => false, 'redemption_id' => (int) ($row['redemption_id'] ?? 0)];
        }
        $redemptionId = (int) ($row['redemption_id'] ?? 0);
        $used = (int) ($row['used_count'] ?? 0);
        if ($used > 0) {
            $db->prepare('UPDATE rateb_pos_coupons SET used_count = GREATEST(0, used_count - 1) WHERE id = :id')
                ->execute(['id' => $couponId]);
        }
        $db->prepare(
            'UPDATE rateb_pos_coupon_redemptions SET reversed_at = NOW() WHERE id = :id AND company_id = :cid'
        )->execute(['id' => $redemptionId, 'cid' => $companyId]);
        return [
            'reversed' => true,
            'redemption_id' => $redemptionId,
            'amount' => (float) ($row['discount_amount'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    private function findCoupon(string $code, int $companyId): ?array
    {
        if (!$this->tableExists('rateb_pos_coupons')) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_coupons WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
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
