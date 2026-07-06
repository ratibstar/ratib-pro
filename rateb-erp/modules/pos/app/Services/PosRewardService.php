<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosCouponBridgeService;
use Rateb\App\Pos\Services\Bridge\PosGiftCardBridgeService;
use Rateb\App\Pos\Services\Bridge\PosLoyaltyBridgeService;
use Rateb\App\Pos\Services\Bridge\PosPromotionBridgeService;
use Rateb\App\Pos\Services\Bridge\PosRewardPolicyBridgeService;

/** Checkout rewards — coupons, loyalty, gift cards, promotion audit. */
class PosRewardService
{
    public function __construct(
        private PosCouponBridgeService $coupons = new PosCouponBridgeService(),
        private PosLoyaltyBridgeService $loyalty = new PosLoyaltyBridgeService(),
        private PosGiftCardBridgeService $giftCards = new PosGiftCardBridgeService(),
        private PosPromotionBridgeService $promotions = new PosPromotionBridgeService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosRewardPolicyBridgeService $policyBridge = new PosRewardPolicyBridgeService(),
        private PosOrderQueryService $queries = new PosOrderQueryService(),
    ) {
    }

    /**
     * @return array{invoice_discount: array<string, mixed>, coupon_id: ?int, coupon_code: ?string, points_redeemed: float}
     */
    public function buildRewardDiscounts(
        array $invoiceDiscount,
        ?string $couponCode,
        float $pointsRedeem,
        int $companyId,
        int $customerId,
        float $netSubtotal
    ): array {
        $extra = 0.0;
        $couponId = null;
        $code = $couponCode !== null ? strtoupper(trim($couponCode)) : '';
        if ($code !== '') {
            $check = $this->coupons->validate($code, $companyId, $netSubtotal);
            if (!$check['ok']) {
                throw new \RuntimeException((string) ($check['error'] ?? __('pos_coupon_invalid')));
            }
            $couponId = (int) ($check['coupon_id'] ?? 0);
            $extra += $this->coupons->discountAmount(
                (string) ($check['discount_type'] ?? 'percent'),
                (float) ($check['discount_value'] ?? 0),
                $netSubtotal
            );
        }
        $pointsRedeemed = 0.0;
        if ($pointsRedeem > 0 && $customerId > 0) {
            $balance = $this->loyalty->balance($companyId, $customerId);
            if ($pointsRedeem > $balance + 0.0001) {
                throw new \RuntimeException(__('pos_loyalty_insufficient'));
            }
            $pointsRedeemed = $pointsRedeem;
            $extra += $this->loyalty->pointsToMoney($pointsRedeem);
        }
        if ($extra > 0) {
            $existing = (float) ($invoiceDiscount['value'] ?? 0);
            $invoiceDiscount = [
                'type' => 'amount',
                'value' => round($existing + $extra, 2),
            ];
        }
        return [
            'invoice_discount' => $invoiceDiscount,
            'coupon_id' => $couponId,
            'coupon_code' => $code !== '' ? $code : null,
            'points_redeemed' => $pointsRedeemed,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $pricing
     * @param array<string, mixed> $rewardMeta
     */
    public function finalizeInTransaction(
        int $orderId,
        int $companyId,
        int $branchId,
        int $userId,
        ?int $customerId,
        array $payments,
        array $lines,
        array $pricing,
        array $rewardMeta
    ): array {
        $pointsEarned = 0.0;
        if ($customerId !== null && $customerId > 0) {
            $redeem = (float) ($rewardMeta['points_redeemed'] ?? 0);
            if ($redeem > 0) {
                $this->loyalty->redeemInTransaction($companyId, $customerId, $redeem, $orderId, $userId);
            }
            $pointsEarned = $this->loyalty->earnInTransaction(
                $companyId,
                $customerId,
                (float) ($pricing['taxable'] ?? $pricing['total'] ?? 0),
                $orderId,
                $userId
            );
        }
        $couponId = (int) ($rewardMeta['coupon_id'] ?? 0);
        if ($couponId > 0) {
            $this->coupons->redeemInTransaction(
                $couponId,
                $orderId,
                $companyId,
                $branchId,
                (float) ($pricing['invoice_discount'] ?? 0)
            );
        }
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $method = strtolower(trim((string) ($payment['method'] ?? '')));
            if ($method !== 'gift_card') {
                continue;
            }
            $code = trim((string) ($payment['reference_no'] ?? $payment['gift_card_code'] ?? ''));
            $amount = (float) ($payment['amount'] ?? 0);
            if ($code === '' || $amount <= 0) {
                throw new \RuntimeException(__('pos_gift_card_invalid'));
            }
            $this->giftCards->redeemInTransaction($code, $amount, $orderId, $companyId, $userId);
        }
        $promotionIds = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $promoId = (int) ($line['promotion_id'] ?? 0);
            if ($promoId < 1) {
                continue;
            }
            $promotionIds[$promoId] = ($promotionIds[$promoId] ?? 0)
                + (float) ($line['promotion_discount'] ?? 0);
            $this->promotions->recordApplicationInTransaction(
                $companyId,
                $promoId,
                $orderId,
                (int) ($line['product_id'] ?? 0) ?: null,
                (float) ($line['promotion_discount'] ?? 0)
            );
        }
        $primaryPromo = $promotionIds !== [] ? (int) array_key_first($promotionIds) : null;
        $this->audit->log('pos_rewards_finalize', 'pos_order', $orderId, [
            'coupon_id' => $couponId > 0 ? $couponId : null,
            'points_redeemed' => (float) ($rewardMeta['points_redeemed'] ?? 0),
            'points_earned' => $pointsEarned,
            'promotion_ids' => array_keys($promotionIds),
            'company_id' => $companyId,
        ]);
        return [
            'points_earned' => $pointsEarned,
            'promotion_id' => $primaryPromo,
        ];
    }

    /** @return array{ok: bool, error?: string, balance?: float} */
    public function validateGiftCard(string $code, int $companyId, float $amount = 0): array
    {
        return $this->giftCards->validate($code, $companyId, $amount);
    }

    /** @return array{ok: bool, error?: string, discount?: float} */
    public function previewCoupon(string $code, int $companyId, float $subtotal): array
    {
        $check = $this->coupons->validate($code, $companyId, $subtotal);
        if (!$check['ok']) {
            return $check;
        }
        return [
            'ok' => true,
            'discount' => $this->coupons->discountAmount(
                (string) ($check['discount_type'] ?? 'percent'),
                (float) ($check['discount_value'] ?? 0),
                $subtotal
            ),
        ];
    }

    public function loyaltyBalance(int $companyId, int $customerId): float
    {
        return $this->loyalty->balance($companyId, $customerId);
    }

    /**
     * Reverse earned/redeemed rewards on return — same TX, idempotent, audited.
     *
     * @param array<string, mixed> $originalOrder
     * @param array<int, array<string, mixed>> $refundRows
     * @return array<string, mixed>
     */
    public function reverseOnReturnInTransaction(
        int $returnOrderId,
        array $originalOrder,
        float $returnTotal,
        int $companyId,
        int $branchId,
        int $userId,
        ?int $customerId,
        array $refundRows
    ): array {
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('pos_rewards_requires_transaction'));
        }
        $originalOrderId = (int) ($originalOrder['id'] ?? 0);
        if ($returnOrderId < 1 || $originalOrderId < 1 || $returnTotal <= 0) {
            return ['skipped' => true];
        }
        if ($this->reversalExists($returnOrderId, 'bundle', null)) {
            return ['skipped' => true, 'idempotent' => true];
        }

        $policy = $this->policyBridge->policy($companyId);
        $originalTotal = (float) ($originalOrder['total'] ?? 0);
        $fullReturn = $this->queries->isOrderFullyReturned(
            $originalOrder,
            $companyId,
            $returnTotal,
            $returnOrderId
        );
        $ratio = $policy['loyalty_pro_rata'] && $originalTotal > 0
            ? min(1.0, round($returnTotal / $originalTotal, 6))
            : ($fullReturn ? 1.0 : 0.0);

        $summary = [
            'return_order_id' => $returnOrderId,
            'original_order_id' => $originalOrderId,
            'ratio' => $ratio,
            'full_return' => $fullReturn,
            'loyalty_earn_reversed' => 0.0,
            'loyalty_redeem_restored' => 0.0,
            'coupon_reversed' => false,
            'gift_card_credits' => [],
        ];

        if ($customerId !== null && $customerId > 0 && $ratio > 0) {
            $earnedOrig = (float) ($originalOrder['loyalty_points_earned'] ?? 0);
            if ($policy['loyalty_clawback_earned'] && $earnedOrig > 0) {
                $earnRev = round($earnedOrig * $ratio, 2);
                if ($earnRev > 0 && !$this->reversalExists($returnOrderId, 'loyalty_earn', null)) {
                    $actual = $this->loyalty->clawbackEarnInTransaction(
                        $companyId,
                        $customerId,
                        $earnRev,
                        $returnOrderId,
                        $userId
                    );
                    $summary['loyalty_earn_reversed'] = $actual;
                    $this->recordReversal(
                        $companyId,
                        $branchId,
                        $returnOrderId,
                        $originalOrderId,
                        'loyalty_earn',
                        null,
                        $actual,
                        null,
                        $userId,
                        ['requested' => $earnRev]
                    );
                }
            }
            $redeemedOrig = (float) ($originalOrder['loyalty_points_redeemed'] ?? 0);
            if ($policy['loyalty_restore_redeemed'] && $redeemedOrig > 0) {
                $restorePts = round($redeemedOrig * $ratio, 2);
                if ($restorePts > 0 && !$this->reversalExists($returnOrderId, 'loyalty_redeem', null)) {
                    $restored = $this->loyalty->restoreRedeemInTransaction(
                        $companyId,
                        $customerId,
                        $restorePts,
                        $returnOrderId,
                        $userId
                    );
                    $summary['loyalty_redeem_restored'] = $restored;
                    $this->recordReversal(
                        $companyId,
                        $branchId,
                        $returnOrderId,
                        $originalOrderId,
                        'loyalty_redeem',
                        null,
                        $restored,
                        null,
                        $userId,
                        ['requested' => $restorePts]
                    );
                }
            }
        }

        $couponId = $this->resolveCouponId($originalOrder, $originalOrderId, $companyId);
        if ($couponId > 0 && !$this->reversalExists($returnOrderId, 'coupon', $couponId)) {
            $couponResult = $this->coupons->reverseRedemptionInTransaction(
                $couponId,
                $originalOrderId,
                $returnOrderId,
                $companyId,
                $fullReturn,
                (bool) $policy['coupon_reversal_full_only']
            );
            if (!empty($couponResult['reversed'])) {
                $summary['coupon_reversed'] = true;
                $this->recordReversal(
                    $companyId,
                    $branchId,
                    $returnOrderId,
                    $originalOrderId,
                    'coupon',
                    $couponId,
                    null,
                    (float) ($couponResult['amount'] ?? 0),
                    $userId,
                    ['redemption_id' => (int) ($couponResult['redemption_id'] ?? 0)]
                );
            }
        }

        if ($policy['gift_card_refund_to_card']) {
            $summary['gift_card_credits'] = $this->reverseGiftCardPayments(
                $returnOrderId,
                $originalOrderId,
                $originalOrder,
                $returnTotal,
                $originalTotal,
                $companyId,
                $branchId,
                $userId,
                $refundRows,
                $ratio
            );
        }

        $this->recordReversal(
            $companyId,
            $branchId,
            $returnOrderId,
            $originalOrderId,
            'bundle',
            null,
            null,
            $returnTotal,
            $userId,
            $summary
        );

        $this->audit->log('pos_rewards_reverse', 'pos_order', $returnOrderId, array_merge($summary, [
            'company_id' => $companyId,
        ]));

        return $summary;
    }

    /**
     * @param array<string, mixed> $originalOrder
     * @param array<int, array<string, mixed>> $refundRows
     * @return array<int, array<string, mixed>>
     */
    private function reverseGiftCardPayments(
        int $returnOrderId,
        int $originalOrderId,
        array $originalOrder,
        float $returnTotal,
        float $originalTotal,
        int $companyId,
        int $branchId,
        int $userId,
        array $refundRows,
        float $ratio
    ): array {
        $credits = [];
        $payments = $this->queries->orderPayments($originalOrderId, $companyId);
        foreach ($payments as $payment) {
            $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
            if ($method !== 'gift_card') {
                continue;
            }
            $code = strtoupper(trim((string) ($payment['reference_no'] ?? '')));
            $paid = (float) ($payment['amount'] ?? 0);
            if ($code === '' || $paid <= 0) {
                continue;
            }
            $cardId = (int) ($payment['id'] ?? 0);
            if ($this->reversalExists($returnOrderId, 'gift_card', $cardId)) {
                continue;
            }
            $creditAmt = $originalTotal > 0
                ? round($paid * ($returnTotal / $originalTotal), 2)
                : round($paid * $ratio, 2);
            if ($creditAmt <= 0) {
                continue;
            }
            $explicitRefund = $this->giftCardRefundAmount($refundRows, $code);
            if ($explicitRefund > 0) {
                $creditAmt = min($creditAmt, $explicitRefund);
            }
            $result = $this->giftCards->creditInTransaction($code, $creditAmt, $returnOrderId, $companyId, $userId);
            $credits[] = array_merge($result, ['code' => $code]);
            $this->recordReversal(
                $companyId,
                $branchId,
                $returnOrderId,
                $originalOrderId,
                'gift_card',
                $cardId,
                null,
                $creditAmt,
                $userId,
                ['gift_card_id' => (int) ($result['gift_card_id'] ?? 0), 'code' => $code]
            );
        }
        foreach ($refundRows as $refund) {
            if (!is_array($refund)) {
                continue;
            }
            $method = strtolower(trim((string) ($refund['refund_method'] ?? $refund['method'] ?? '')));
            if ($method !== 'gift_card') {
                continue;
            }
            $code = strtoupper(trim((string) ($refund['reference_no'] ?? '')));
            $amount = (float) ($refund['amount'] ?? 0);
            if ($code === '' || $amount <= 0) {
                continue;
            }
            $refId = (int) ($refund['id'] ?? 0);
            if ($refId > 0 && $this->reversalExists($returnOrderId, 'gift_card_refund', $refId)) {
                continue;
            }
            if ($this->alreadyCreditedCode($credits, $code)) {
                continue;
            }
            $result = $this->giftCards->creditInTransaction($code, $amount, $returnOrderId, $companyId, $userId);
            $credits[] = array_merge($result, ['code' => $code]);
            $this->recordReversal(
                $companyId,
                $branchId,
                $returnOrderId,
                $originalOrderId,
                'gift_card_refund',
                $refId > 0 ? $refId : null,
                null,
                $amount,
                $userId,
                ['gift_card_id' => (int) ($result['gift_card_id'] ?? 0), 'code' => $code]
            );
        }
        $giftCode = strtoupper(trim((string) ($originalOrder['gift_card_code'] ?? '')));
        if ($giftCode !== '' && !$this->alreadyCreditedCode($credits, $giftCode) && $payments === []) {
            $creditAmt = round($returnTotal * $ratio, 2);
            if ($creditAmt > 0 && !$this->reversalExists($returnOrderId, 'gift_card', 0)) {
                $result = $this->giftCards->creditInTransaction($giftCode, $creditAmt, $returnOrderId, $companyId, $userId);
                $credits[] = array_merge($result, ['code' => $giftCode]);
                $this->recordReversal(
                    $companyId,
                    $branchId,
                    $returnOrderId,
                    $originalOrderId,
                    'gift_card',
                    0,
                    null,
                    $creditAmt,
                    $userId,
                    ['gift_card_id' => (int) ($result['gift_card_id'] ?? 0), 'code' => $giftCode]
                );
            }
        }
        return $credits;
    }

    /** @param array<int, array<string, mixed>> $refundRows */
    private function giftCardRefundAmount(array $refundRows, string $code): float
    {
        $sum = 0.0;
        foreach ($refundRows as $refund) {
            if (!is_array($refund)) {
                continue;
            }
            $method = strtolower(trim((string) ($refund['refund_method'] ?? $refund['method'] ?? '')));
            $refCode = strtoupper(trim((string) ($refund['reference_no'] ?? '')));
            if ($method === 'gift_card' && ($refCode === '' || $refCode === $code)) {
                $sum += (float) ($refund['amount'] ?? 0);
            }
        }
        return round($sum, 2);
    }

    /** @param array<int, array<string, mixed>> $credits */
    private function alreadyCreditedCode(array $credits, string $code): bool
    {
        foreach ($credits as $row) {
            if (strtoupper(trim((string) ($row['code'] ?? ''))) === $code) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $originalOrder */
    private function resolveCouponId(array $originalOrder, int $originalOrderId, int $companyId): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT coupon_id FROM rateb_pos_coupon_redemptions
             WHERE company_id = :cid AND order_id = :oid AND reversed_at IS NULL LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'oid' => $originalOrderId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $code = strtoupper(trim((string) ($originalOrder['coupon_code'] ?? '')));
        if ($code === '') {
            return 0;
        }
        $stmt = $db->prepare(
            'SELECT id FROM rateb_pos_coupons WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : 0;
    }

    private function reversalExists(int $returnOrderId, string $kind, ?int $referenceId): bool
    {
        if (!$this->tableExists('rateb_pos_reward_reversals')) {
            return false;
        }
        $db = Database::connection();
        if ($referenceId === null) {
            $stmt = $db->prepare(
                'SELECT id FROM rateb_pos_reward_reversals
                 WHERE return_order_id = :rid AND reversal_kind = :k AND reference_id IS NULL LIMIT 1'
            );
            $stmt->execute(['rid' => $returnOrderId, 'k' => $kind]);
        } else {
            $stmt = $db->prepare(
                'SELECT id FROM rateb_pos_reward_reversals
                 WHERE return_order_id = :rid AND reversal_kind = :k AND reference_id = :ref LIMIT 1'
            );
            $stmt->execute(['rid' => $returnOrderId, 'k' => $kind, 'ref' => $referenceId]);
        }
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string, mixed>|null $metadata */
    private function recordReversal(
        int $companyId,
        int $branchId,
        int $returnOrderId,
        int $originalOrderId,
        string $kind,
        ?int $referenceId,
        ?float $points,
        ?float $amount,
        int $userId,
        ?array $metadata = null
    ): void {
        if (!$this->tableExists('rateb_pos_reward_reversals')) {
            return;
        }
        try {
            Database::connection()->prepare(
                'INSERT INTO rateb_pos_reward_reversals
                 (company_id, branch_id, return_order_id, original_order_id, reversal_kind, reference_id, points, amount, metadata_json, created_by)
                 VALUES (:cid, :bid, :rid, :oid, :k, :ref, :pts, :amt, :meta, :uid)'
            )->execute([
                'cid' => $companyId,
                'bid' => $branchId > 0 ? $branchId : null,
                'rid' => $returnOrderId,
                'oid' => $originalOrderId,
                'k' => $kind,
                'ref' => $referenceId,
                'pts' => $points,
                'amt' => $amount,
                'meta' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                'uid' => $userId > 0 ? $userId : null,
            ]);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000' && !str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw $e;
            }
        }
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
