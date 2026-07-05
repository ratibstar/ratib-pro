<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;
use Rateb\App\Pos\Models\PosOrderLine;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;

/** Suspend and resume sales (no stock movement). */
final class PosSuspendService
{
    public function __construct(
        private PosRegisterCartService $cart = new PosRegisterCartService(),
        private PosSellPriceService $sellPrices = new PosSellPriceService(),
        private PosPricingService $pricing = new PosPricingService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $scope
     */
    public function suspend(
        array $lines,
        array $scope,
        ?array $customer = null,
        string $notes = ''
    ): array {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $branchId = (int) ($scope['branch_id'] ?? 0);
        $userId = (int) ($scope['user_id'] ?? 0);
        if ($companyId < 1 || $branchId < 1 || $userId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $normalized = $this->cart->normalizeLines($lines);
        if ($normalized === []) {
            throw new \RuntimeException(__('pos_cart_empty'));
        }
        TenantContext::setCompanyId($companyId);
        $priced = $this->sellPrices->applyToLines($normalized, $companyId, $branchId, $customer);
        $totals = $this->pricing->calculate($priced);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $orderNo = (new PosOrder())->generateDocumentCode(PosDocumentCodes::SUSPEND, 'order_no');
            $payload = json_encode([
                'lines' => $priced,
                'customer' => $customer,
                'totals' => $totals,
            ], JSON_UNESCAPED_UNICODE);

            $orderId = (new PosOrder())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => (int) ($scope['warehouse_id'] ?? 0) ?: null,
                'terminal_id' => (int) ($scope['terminal_id'] ?? 0) ?: null,
                'shift_id' => (int) ($scope['shift_id'] ?? 0) ?: null,
                'session_id' => (int) ($scope['session_id'] ?? 0) ?: null,
                'order_no' => $orderNo,
                'order_type' => 'suspended',
                'status' => 'suspended',
                'customer_id' => !empty($customer['id']) ? (int) $customer['id'] : null,
                'created_by' => $userId,
                'notes' => $notes !== '' ? $notes : null,
                'suspended_payload' => $payload,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
            ]);

            $lineNo = 1;
            foreach ($priced as $line) {
                (new PosOrderLine())->create([
                    'order_id' => $orderId,
                    'company_id' => $companyId,
                    'inventory_id' => (int) ($line['product_id'] ?? 0),
                    'line_no' => $lineNo++,
                    'line_kind' => 'sale',
                    'description' => trim((string) ($line['item_name'] ?? '')),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'line_total' => (float) ($line['line_total'] ?? 0),
                ]);
            }

            $db->commit();
            $this->audit->log('pos_suspend', 'pos_order', $orderId, [
                'order_no' => $orderNo,
                'total' => $totals['total'],
                'company_id' => $companyId,
            ]);

            return [
                'ok' => true,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'totals' => $totals,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listSuspended(int $companyId, int $branchId, int $limit = 50): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, order_no, total, customer_id, notes, created_at
             FROM rateb_pos_orders
             WHERE company_id = :cid AND branch_id = :bid
               AND order_type = :ot AND status = :st
             ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'ot' => 'suspended',
            'st' => 'suspended',
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    public function resume(int $orderId, int $companyId, int $branchId, int $userId): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM rateb_pos_orders
                 WHERE id = :id AND company_id = :cid AND branch_id = :bid
                   AND order_type = :ot AND status = :st FOR UPDATE'
            );
            $stmt->execute([
                'id' => $orderId,
                'cid' => $companyId,
                'bid' => $branchId,
                'ot' => 'suspended',
                'st' => 'suspended',
            ]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$order) {
                throw new \RuntimeException(__('no_records'));
            }
            $payload = json_decode((string) ($order['suspended_payload'] ?? ''), true);
            if (!is_array($payload) || !is_array($payload['lines'] ?? null)) {
                throw new \RuntimeException(__('invalid_request'));
            }

            (new PosOrder())->update($orderId, [
                'status' => 'void',
                'notes' => trim((string) ($order['notes'] ?? '') . ' [resumed by user #' . $userId . ']'),
            ]);

            $db->commit();
            $this->audit->log('pos_suspend_resume', 'pos_order', $orderId, [
                'order_no' => $order['order_no'] ?? '',
                'company_id' => $companyId,
            ]);

            return [
                'ok' => true,
                'lines' => $payload['lines'],
                'customer' => $payload['customer'] ?? null,
                'totals' => $payload['totals'] ?? [],
                'suspended_order_id' => $orderId,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
