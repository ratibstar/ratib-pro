<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;
use Rateb\App\Pos\Models\PosOrderLine;
use Rateb\App\Pos\Models\PosReturnRecord;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosAccountingBridgeService;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;

/** Return processing — stock IN, refunds, audit (single transaction). */
final class PosReturnService
{
    public function __construct(
        private PosOrderQueryService $queries = new PosOrderQueryService(),
        private PosPricingService $pricing = new PosPricingService(),
        private PosRefundService $refunds = new PosRefundService(),
        private PosReceiptService $receipts = new PosReceiptService(),
        private PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosAccountingBridgeService $accounting = new PosAccountingBridgeService(),
        private PosRewardService $rewards = new PosRewardService(),
        private PosCashDrawerService $drawers = new PosCashDrawerService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $returnLines [{original_line_id, quantity, reason?}]
     * @param array<int, array<string, mixed>> $refundPayments
     * @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    public function process(
        int $originalOrderId,
        array $returnLines,
        array $refundPayments,
        array $scope,
        ?array $customer = null
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);
        $userId = (int) ($scope['user_id'] ?? 0);
        if ($companyId < 1 || $branchId < 1 || $userId < 1 || $originalOrderId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        TenantContext::setCompanyId($companyId);

        $original = $this->queries->findOrder($originalOrderId, $companyId, $branchId);
        if (!$original || (string) ($original['order_type'] ?? '') !== 'sale' || (string) ($original['status'] ?? '') !== 'completed') {
            throw new \RuntimeException(__('pos_return_invalid_order'));
        }

        $originalLines = $this->queries->orderLines($originalOrderId, $companyId);
        $linesById = [];
        foreach ($originalLines as $ol) {
            $linesById[(int) ($ol['id'] ?? 0)] = $ol;
        }

        $pricedCart = [];
        $stockLines = [];
        foreach ($returnLines as $req) {
            if (!is_array($req)) {
                continue;
            }
            $origLineId = (int) ($req['original_line_id'] ?? 0);
            $qty = (float) ($req['quantity'] ?? 0);
            if ($origLineId < 1 || $qty <= 0 || !isset($linesById[$origLineId])) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $orig = $linesById[$origLineId];
            $unit = (float) ($orig['unit_price'] ?? 0);
            $lineId = bin2hex(random_bytes(8));
            $pricedCart[] = [
                'id' => $lineId,
                'product_id' => (int) ($orig['inventory_id'] ?? 0),
                'quantity' => $qty,
                'unit_price' => $unit,
                'item_name' => (string) ($orig['description'] ?? ''),
            ];
            $stockLines[] = [
                'inventory_id' => (int) ($orig['inventory_id'] ?? 0),
                'product_id' => (int) ($orig['inventory_id'] ?? 0),
                'quantity' => $qty,
                'serial_no' => (string) ($orig['serial_no'] ?? ''),
                'serial_id' => (int) ($orig['serial_id'] ?? 0) ?: null,
                'batch_id' => (int) ($orig['batch_id'] ?? 0),
                'original_line_id' => $origLineId,
                'order_line_id' => null,
                'batch_restorations' => [],
                'return_reason' => trim((string) ($req['reason'] ?? '')),
                'line_id' => $lineId,
            ];
        }
        if ($pricedCart === []) {
            throw new \RuntimeException(__('pos_return_empty'));
        }

        $pricing = $this->pricing->calculate($pricedCart, [], (float) ($scope['tax_rate'] ?? 0.15));
        $refundPayments = $this->normalizeRefunds($refundPayments, (float) $pricing['total']);
        $customerId = !empty($customer['id']) ? (int) $customer['id'] : (int) ($original['customer_id'] ?? 0);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->queries->lockOriginalOrderForReturn($originalOrderId, $companyId, $branchId);
            $this->queries->assertReturnLineQuantities($returnLines, $linesById, $companyId);
            foreach ($stockLines as &$stockLine) {
                $origLineId = (int) ($stockLine['original_line_id'] ?? 0);
                $qty = (float) ($stockLine['quantity'] ?? 0);
                if ($origLineId > 0 && $qty > 0) {
                    $stockLine['batch_restorations'] = $this->queries->computeReturnBatchAllocations(
                        $origLineId,
                        $qty,
                        $companyId
                    );
                }
            }
            unset($stockLine);

            $orderNo = (new PosOrder())->generateDocumentCode(PosDocumentCodes::RETURN, 'order_no');
            $orderId = (new PosOrder())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => (int) ($scope['warehouse_id'] ?? 0) ?: null,
                'terminal_id' => (int) ($scope['terminal_id'] ?? 0) ?: null,
                'shift_id' => (int) ($scope['shift_id'] ?? 0) ?: null,
                'session_id' => (int) ($scope['session_id'] ?? 0) ?: null,
                'order_no' => $orderNo,
                'order_type' => 'return',
                'status' => 'processing',
                'customer_id' => $customerId > 0 ? $customerId : null,
                'original_order_id' => $originalOrderId,
                'created_by' => $userId,
                'subtotal' => $pricing['subtotal'],
                'discount_total' => $pricing['discount_total'],
                'tax' => $pricing['tax'],
                'total' => $pricing['total'],
            ]);

            $lineNo = 1;
            $orderLinesForReceipt = [];
            $stockPostLines = [];
            foreach ($pricing['lines'] as $pricedLine) {
                $stockLine = $this->findStockLine($stockLines, (string) ($pricedLine['id'] ?? ''));
                if ($stockLine === null) {
                    continue;
                }
                $orig = $linesById[(int) $stockLine['original_line_id']];
                $returnBatchJson = $this->encodeReturnBatchJson(
                    $orig,
                    (float) ($pricedLine['quantity'] ?? 0),
                    $stockLine['batch_restorations'] ?? []
                );
                $orderLineId = (new PosOrderLine())->create([
                    'order_id' => $orderId,
                    'company_id' => $companyId,
                    'inventory_id' => (int) ($orig['inventory_id'] ?? 0),
                    'batch_id' => (int) ($orig['batch_id'] ?? 0) ?: null,
                    'batch_allocations_json' => $returnBatchJson,
                    'serial_no' => trim((string) ($orig['serial_no'] ?? '')) ?: null,
                    'serial_id' => (int) ($orig['serial_id'] ?? 0) ?: null,
                    'line_no' => $lineNo++,
                    'line_kind' => 'return',
                    'original_line_id' => (int) $stockLine['original_line_id'],
                    'return_reason' => $stockLine['return_reason'] !== '' ? $stockLine['return_reason'] : null,
                    'description' => (string) ($orig['description'] ?? ''),
                    'quantity' => (float) ($pricedLine['quantity'] ?? 0),
                    'unit_price' => (float) ($pricedLine['unit_price'] ?? 0),
                    'discount_amount' => (float) ($pricedLine['discount_amount'] ?? 0),
                    'tax_amount' => (float) ($pricedLine['tax_amount'] ?? 0),
                    'line_total' => (float) ($pricedLine['line_total'] ?? 0),
                ]);
                $orderLinesForReceipt[] = array_merge($orig, ['id' => $orderLineId]);
                $stockPostLines[] = array_merge($stockLine, ['order_line_id' => $orderLineId]);
            }

            $this->inventory->postReturnForOrderInTransaction(
                $orderId,
                $orderNo,
                $companyId,
                (int) ($scope['warehouse_id'] ?? 0) ?: null,
                $stockPostLines
            );

            $refundRows = $this->refunds->assertAndPersist(
                (float) $pricing['total'],
                $refundPayments,
                $orderId,
                $originalOrderId,
                $companyId,
                $branchId,
                $userId,
                $customerId > 0 ? $customerId : null
            );

            $shiftId = (int) ($scope['shift_id'] ?? 0);
            if ($shiftId > 0) {
                $cashOut = PosCashDrawerService::sumCashRefunds($refundRows);
                if ($cashOut > 0) {
                    $this->drawers->applyCashDeltaInTransaction($shiftId, $companyId, -$cashOut);
                }
            }

            $rewardReversal = $this->rewards->reverseOnReturnInTransaction(
                $orderId,
                $original,
                (float) $pricing['total'],
                $companyId,
                $branchId,
                $userId,
                $customerId > 0 ? $customerId : null,
                $refundRows
            );

            $returnLineRows = $this->loadReturnOrderLines($orderId, $companyId);
            $this->accounting->postReturnInTransaction(
                $orderId,
                ['order_no' => $orderNo, 'created_at' => date('Y-m-d H:i:s')],
                $pricing,
                $refundRows,
                $returnLineRows,
                $companyId,
                $branchId
            );

            $returnRecordId = (new PosReturnRecord())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'original_order_id' => $originalOrderId,
                'order_id' => $orderId,
                'return_no' => $orderNo,
                'status' => 'completed',
                'total' => $pricing['total'],
            ]);

            $completedAt = date('Y-m-d H:i:s');
            $orderRow = (new PosOrder())->find($orderId) ?? [];
            $receipt = $this->receipts->buildReturn(
                array_merge($orderRow, ['completed_at' => $completedAt, 'original_order_no' => $original['order_no'] ?? '']),
                $orderLinesForReceipt,
                $refundRows,
                $pricing,
                ['customer' => $customer]
            );

            (new PosOrder())->update($orderId, [
                'status' => 'completed',
                'completed_at' => $completedAt,
                'receipt_json' => json_encode($receipt, JSON_UNESCAPED_UNICODE),
            ]);

            $db->commit();

            $this->audit->log('pos_return', 'pos_order', $orderId, [
                'return_no' => $orderNo,
                'original_order_id' => $originalOrderId,
                'total' => $pricing['total'],
                'return_record_id' => $returnRecordId,
                'reward_reversal' => $rewardReversal,
                'company_id' => $companyId,
            ]);

            return [
                'ok' => true,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'pricing' => $pricing,
                'refunds' => $refundRows,
                'receipt' => $receipt,
                'reward_reversal' => $rewardReversal,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<int, array<string, mixed>> $stockLines */
    private function findStockLine(array $stockLines, string $lineId): ?array
    {
        foreach ($stockLines as $line) {
            if ((string) ($line['line_id'] ?? '') === $lineId) {
                return $line;
            }
        }
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadReturnOrderLines(int $orderId, int $companyId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_order_lines WHERE order_id = :oid AND company_id = :cid ORDER BY line_no'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<int, array<string, mixed>> $refunds @return array<int, array<string, mixed>> */
    private function normalizeRefunds(array $refunds, float $total): array
    {
        $sum = 0.0;
        foreach ($refunds as $r) {
            if (is_array($r)) {
                $sum += (float) ($r['amount'] ?? 0);
            }
        }
        if ($sum > 0.02) {
            return $refunds;
        }
        $method = 'cash';
        foreach ($refunds as $r) {
            if (is_array($r) && !empty($r['method'])) {
                $method = strtolower(trim((string) $r['method']));
                break;
            }
        }
        return [['method' => $method, 'amount' => round($total, 2), 'reference_no' => '']];
    }

    /**
     * @param array<string, mixed> $orig
     * @param array<int, array<string, mixed>> $restorations
     */
    private function encodeReturnBatchJson(array $orig, float $returnQty, array $restorations): ?string
    {
        if ($restorations !== []) {
            return json_encode($restorations, JSON_UNESCAPED_UNICODE);
        }
        $batchId = (int) ($orig['batch_id'] ?? 0);
        if ($batchId > 0 && $returnQty > 0) {
            return json_encode([['batch_id' => $batchId, 'quantity' => round($returnQty, 3)]], JSON_UNESCAPED_UNICODE);
        }
        return null;
    }
}
