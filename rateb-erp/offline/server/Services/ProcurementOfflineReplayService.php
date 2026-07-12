<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Rfq;
use Rateb\App\Services\DocumentCodeService;
use Rateb\App\Services\ProcurementService;

/**
 * Thin Procurement offline replay — Phase 5 drafts + Phase 14.2 GRN via ProcurementService::receiveOrder.
 * No duplicated GRN business logic; no approvals / supplier payments outside existing services.
 */
final class ProcurementOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'purchase_request.draft',
            'purchase_request.create',
            'pr.draft',
            'procurement.purchase_request.draft',
            'rfq.draft',
            'rfq.create',
            'procurement.rfq.draft',
            'purchase_order.draft',
            'purchase_order.create',
            'po.draft',
            'procurement.purchase_order.draft',
            'goods_receipt.receive',
            'grn.receive',
            'procurement.goods_receipt.receive',
            'purchase_order.receive',
        ];
    }

    /**
     * @return list<string>
     */
    public static function goodsReceiptActions(): array
    {
        return [
            'goods_receipt.receive',
            'grn.receive',
            'procurement.goods_receipt.receive',
            'purchase_order.receive',
        ];
    }

    public function __construct(
        private ?ProcurementOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): ProcurementOfflineTenantGuard
    {
        return $this->guard ??= new ProcurementOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_procurement_action'];
        }

        if (in_array($action, self::goodsReceiptActions(), true)
            && !(new OfflineFeatureFlagService())->isProcurementGoodsReceiptEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_grn_offline_disabled'];
        }

        try {
            $scope = $this->buildScope($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    public function replay(string $action, array $scope, array $inner, string $idempotencyKey = ''): array
    {
        $action = $this->normalizeAction($action);

        if (in_array($action, self::goodsReceiptActions(), true)) {
            if (!(new OfflineFeatureFlagService())->isProcurementGoodsReceiptEnabled()) {
                throw new \RuntimeException('procurement_grn_offline_disabled');
            }

            return $this->goodsReceiptReceive($scope, $inner, $idempotencyKey);
        }

        return match ($action) {
            'purchase_request.draft', 'purchase_request.create', 'pr.draft', 'procurement.purchase_request.draft'
                => $this->purchaseRequestDraft($scope, $inner, $idempotencyKey),
            'rfq.draft', 'rfq.create', 'procurement.rfq.draft'
                => $this->rfqDraft($scope, $inner, $idempotencyKey),
            'purchase_order.draft', 'purchase_order.create', 'po.draft', 'procurement.purchase_order.draft'
                => $this->purchaseOrderDraft($scope, $inner, $idempotencyKey),
            default => throw new \RuntimeException('unknown_procurement_action'),
        };
    }

    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveConflict(array $clientItem, ?array $serverItem): array
    {
        return $this->resolver()->resolveProcurement($clientItem, $serverItem);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function purchaseRequestDraft(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->purchaseRequestExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'purchase_request_id' => $existingId, 'draft' => true];
            }
        }

        $title = trim((string) ($inner['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('empty_purchase_request_payload');
        }

        if (isset($inner['expected_status']) || isset($inner['server_version'])) {
            // Soft conflict probe when client expected a prior draft status (rare for create).
            $decision = $this->resolver()->resolveProcurement(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_status'] ?? 'draft',
                ],
                isset($inner['server_status'])
                    ? ['version' => (int) ($inner['server_version'] ?? 0), 'status' => $inner['server_status']]
                    : null
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }

        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $priorityRaw = strtolower(trim((string) ($inner['priority'] ?? 'medium')));
        $priorityMap = [
            'normal' => 'medium',
            'med' => 'medium',
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'urgent' => 'urgent',
        ];
        $priority = $priorityMap[$priorityRaw] ?? 'medium';

        $model = new PurchaseRequest();
        $payload = [
            'company_id' => $scope['company_id'],
            'request_no' => $model->generateRequestNo(),
            'title' => $title,
            'department' => trim((string) ($inner['department'] ?? '')) ?: null,
            'priority' => $priority,
            'status' => 'draft',
            'expected_date' => $this->nullableDate($inner['expected_date'] ?? null),
            'requested_by' => $scope['user_id'] > 0 ? $scope['user_id'] : null,
            'total_estimated' => (float) ($inner['total_estimated'] ?? 0),
            'currency' => trim((string) ($inner['currency'] ?? 'SAR')) ?: 'SAR',
            'notes' => $notes !== '' ? $notes : null,
        ];
        if ($scope['branch_id'] > 0) {
            $payload['branch_id'] = $scope['branch_id'];
        }

        $id = $model->create($payload);

        $lines = $this->normalizePurchaseRequestLines(is_array($inner['lines'] ?? null) ? $inner['lines'] : []);
        if ($lines !== []) {
            $total = LineItems::syncPurchaseRequestItems($id, $lines);
            $model->update($id, ['total_estimated' => $total]);
        }

        return ['ok' => true, 'purchase_request_id' => $id, 'status' => 'draft', 'draft' => true];
    }

    /**
     * Map offline/UI line shapes onto rateb_purchase_request_items columns.
     *
     * @param array<int, mixed> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizePurchaseRequestLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $name = trim((string) ($line['item_name'] ?? $line['description'] ?? $line['name'] ?? ''));
            if ($name === '') {
                $name = 'Item';
            }
            $qty = (float) ($line['quantity'] ?? $line['qty'] ?? 1);
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $row = [
                'item_name' => $name,
                'description' => trim((string) ($line['description'] ?? '')) ?: null,
                'quantity' => $qty,
                'unit' => trim((string) ($line['unit'] ?? 'unit')) ?: 'unit',
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'tax_name' => trim((string) ($line['tax_name'] ?? 'Local Sales 0%')) ?: 'Local Sales 0%',
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'excluding_tax' => isset($line['excluding_tax']) ? ((int) $line['excluding_tax'] ? 1 : 0) : 1,
                'total_price' => 0.0,
            ];
            foreach (['inventory_id', 'supplier_id', 'warehouse_id', 'account_id'] as $fk) {
                if (isset($line[$fk]) && (int) $line[$fk] > 0) {
                    $row[$fk] = (int) $line[$fk];
                }
            }
            $row['total_price'] = round($qty * (float) $row['unit_price'], 2);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function rfqDraft(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->rfqExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'rfq_id' => $existingId, 'draft' => true];
            }
        }

        $title = trim((string) ($inner['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('empty_rfq_payload');
        }

        $description = trim((string) ($inner['description'] ?? ''));
        if ($idempotencyKey !== '') {
            $description = trim($description . ' [offline:' . $idempotencyKey . ']');
        }

        $model = new Rfq();
        $data = [
            'company_id' => $scope['company_id'],
            'title' => $title,
            'status' => 'draft',
            'deadline' => $this->nullableDate($inner['deadline'] ?? null),
            'description' => $description !== '' ? $description : null,
        ];
        if ($scope['branch_id'] > 0) {
            $data['branch_id'] = $scope['branch_id'];
        }
        (new DocumentCodeService())->assignIfEmpty($data, $model, DocumentCodeService::PREFIX_RFQ, 'rfq_no');

        $id = $model->create($data);

        return ['ok' => true, 'rfq_id' => $id, 'status' => 'draft', 'draft' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function purchaseOrderDraft(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->purchaseOrderExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'purchase_order_id' => $existingId, 'draft' => true];
            }
        }

        $supplierId = (int) ($inner['supplier_id'] ?? 0);
        $assert = $this->guard()->assertSupplier($supplierId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        $warehouseId = isset($inner['warehouse_id']) ? (int) $inner['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId < 1) {
            $warehouseId = null;
        }
        $wh = $this->guard()->assertWarehouse($warehouseId, $scope);
        if (!$wh['ok']) {
            throw new \RuntimeException((string) ($wh['error'] ?? 'warehouse_tenant_mismatch'));
        }

        $prId = isset($inner['purchase_request_id']) ? (int) $inner['purchase_request_id'] : 0;
        if ($prId > 0) {
            $pr = $this->guard()->assertPurchaseRequest($prId, $scope);
            if (!$pr['ok']) {
                throw new \RuntimeException((string) ($pr['error'] ?? 'tenant_mismatch'));
            }
        }

        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $model = new PurchaseOrder();
        $payload = [
            'company_id' => $scope['company_id'],
            'order_no' => $model->generateOrderNo(),
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'purchase_request_id' => $prId > 0 ? $prId : null,
            'quotation_id' => isset($inner['quotation_id']) ? (int) $inner['quotation_id'] : null,
            'status' => 'draft',
            'order_date' => $this->nullableDate($inner['order_date'] ?? date('Y-m-d')) ?? date('Y-m-d'),
            'expected_date' => $this->nullableDate($inner['expected_date'] ?? null),
            'subtotal' => (float) ($inner['subtotal'] ?? 0),
            'tax_amount' => (float) ($inner['tax_amount'] ?? 0),
            'total_amount' => (float) ($inner['total_amount'] ?? 0),
            'currency' => trim((string) ($inner['currency'] ?? 'SAR')) ?: 'SAR',
            'notes' => $notes !== '' ? $notes : null,
        ];
        if ($scope['branch_id'] > 0) {
            $payload['branch_id'] = $scope['branch_id'];
        }

        $id = $model->create($payload);

        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        if ($lines !== []) {
            $total = LineItems::syncPurchaseOrderItems($id, $lines);
            $model->update($id, [
                'subtotal' => $total,
                'total_amount' => $total + (float) ($payload['tax_amount'] ?? 0),
            ]);
        }

        return ['ok' => true, 'purchase_order_id' => $id, 'status' => 'draft', 'draft' => true];
    }

    /**
     * Phase 14.2 — PO goods receipt via existing ProcurementService::receiveOrder only.
     *
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function goodsReceiptReceive(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingPo = $this->guard()->goodsReceiptExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingPo !== null && $existingPo > 0) {
                return [
                    'ok' => true,
                    'idempotent' => true,
                    'purchase_order_id' => $existingPo,
                    'goods_receipt' => true,
                ];
            }
        }

        $orderId = (int) ($inner['purchase_order_id'] ?? $inner['order_id'] ?? 0);
        $assert = $this->guard()->assertPurchaseOrder($orderId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        $warehouseId = isset($inner['warehouse_id']) ? (int) $inner['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId < 1) {
            $warehouseId = null;
        }
        $wh = $this->guard()->assertWarehouse($warehouseId, $scope);
        if (!$wh['ok']) {
            throw new \RuntimeException((string) ($wh['error'] ?? 'warehouse_tenant_mismatch'));
        }

        $receiveQtys = $this->normalizeReceiveQtys($inner);
        if ($receiveQtys === []) {
            throw new \RuntimeException('empty_goods_receipt_payload');
        }

        (new ProcurementService())->receiveOrder($orderId, $receiveQtys, $warehouseId);

        if ($idempotencyKey !== '') {
            $this->guard()->stampGoodsReceiptMovements($scope['company_id'], $orderId, $idempotencyKey);
        }

        $order = (new PurchaseOrder())->find($orderId);

        return [
            'ok' => true,
            'purchase_order_id' => $orderId,
            'status' => (string) ($order['status'] ?? ''),
            'goods_receipt' => true,
        ];
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<int|string, float>
     */
    private function normalizeReceiveQtys(array $inner): array
    {
        $raw = $inner['receive_qty'] ?? $inner['receive_qtys'] ?? $inner['quantities'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $lineId => $qty) {
            $id = (int) $lineId;
            $q = (float) $qty;
            if ($id > 0 && $q > 0) {
                $out[$id] = $q;
            }
        }

        return $out;
    }

    private function nullableDate(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return $v;
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'server_newer',
            'status_changed',
            'branch_mismatch',
            'tenant_mismatch',
            'procurement_conflict',
        ], true);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        $aliases = [
            'create_purchase_request' => 'purchase_request.draft',
            'create_pr' => 'purchase_request.draft',
            'create_rfq' => 'rfq.draft',
            'create_purchase_order' => 'purchase_order.draft',
            'create_po' => 'purchase_order.draft',
            'po.create' => 'purchase_order.draft',
            'pr.create' => 'purchase_request.draft',
            'receive_goods' => 'goods_receipt.receive',
            'grn.create' => 'goods_receipt.receive',
            'po.receive' => 'goods_receipt.receive',
        ];

        return $aliases[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $raw = $queueRow['payload'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return ['action' => $queueRow['action'] ?? null, 'payload' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['action' => $queueRow['action'] ?? null, 'payload' => []];
    }

    /**
     * Scope from queue row only — never from client payload (H-BRANCH-001).
     *
     * @param array<string, mixed> $queueRow
     * @return array{company_id: int, branch_id: int, user_id: int, device_id: string}
     */
    private function buildScope(array $queueRow): array
    {
        return (new OfflineReplayScopeService())->fromQueueRow($queueRow);
    }
}
