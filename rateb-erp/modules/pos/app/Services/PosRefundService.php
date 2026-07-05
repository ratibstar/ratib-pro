<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Models\PosRefund;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosStoreCreditBridgeService;

/** Refund persistence and validation (no GL). */
final class PosRefundService
{
    private const REFUND_METHODS = ['cash', 'card', 'bank', 'wallet', 'store_credit', 'gift_card'];

    public function __construct(
        private PosStoreCreditBridgeService $storeCredit = new PosStoreCreditBridgeService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $refunds
     * @return array<int, array<string, mixed>>
     */
    public function assertAndPersist(
        float $refundTotal,
        array $refunds,
        int $orderId,
        int $originalOrderId,
        int $companyId,
        int $branchId,
        int $userId,
        ?int $customerId = null
    ): array {
        if ($refundTotal <= 0) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $sum = 0.0;
        $rows = [];
        foreach ($refunds as $refund) {
            if (!is_array($refund)) {
                continue;
            }
            $method = strtolower(trim((string) ($refund['method'] ?? '')));
            if (!in_array($method, self::REFUND_METHODS, true)) {
                throw new \RuntimeException(__('pos_refund_invalid_method'));
            }
            $amount = (float) ($refund['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \RuntimeException(__('pos_refund_invalid_amount'));
            }
            if ($method === 'store_credit' && ($customerId === null || $customerId < 1)) {
                throw new \RuntimeException(__('pos_store_credit_customer_required'));
            }
            $sum += $amount;
        }
        if (abs($sum - $refundTotal) > 0.02) {
            throw new \RuntimeException(__('pos_refund_mismatch'));
        }

        foreach ($refunds as $refund) {
            if (!is_array($refund)) {
                continue;
            }
            $method = strtolower(trim((string) ($refund['method'] ?? 'cash')));
            $amount = round((float) ($refund['amount'] ?? 0), 2);
            $refundId = (new PosRefund())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'order_id' => $orderId,
                'original_order_id' => $originalOrderId > 0 ? $originalOrderId : null,
                'refund_method' => $method,
                'amount' => $amount,
                'reference_no' => trim((string) ($refund['reference_no'] ?? '')) ?: null,
                'status' => 'completed',
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            if ($method === 'store_credit' && $customerId !== null && $customerId > 0) {
                $this->storeCredit->creditInTransaction(
                    $companyId,
                    $customerId,
                    $amount,
                    $orderId,
                    $refundId,
                    $userId,
                    'Return refund'
                );
            }
            $rows[] = [
                'id' => $refundId,
                'refund_method' => $method,
                'amount' => $amount,
                'reference_no' => trim((string) ($refund['reference_no'] ?? '')),
            ];
            $this->audit->log('pos_refund', 'pos_refund', $refundId, [
                'order_id' => $orderId,
                'method' => $method,
                'amount' => $amount,
                'company_id' => $companyId,
            ]);
        }
        return $rows;
    }
}
