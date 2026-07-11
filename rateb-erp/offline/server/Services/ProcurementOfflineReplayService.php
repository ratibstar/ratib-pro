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

/**
 * Thin Procurement offline replay — drafts only via existing models / LineItems / DocumentCodeService.
 * No approvals, financial posting, supplier payments, or accounting entries (Phase 5 / Tier 1).
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
        $scope = $this->buildScope($queueRow, $inner);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_procurement_action'];
        }

        if ($scope['company_id'] < 1) {
            return ['status' => 'failed', 'error' => 'company_required'];
        }

        try {
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

        $model = new PurchaseRequest();
        $payload = [
            'company_id' => $scope['company_id'],
            'request_no' => $model->generateRequestNo(),
            'title' => $title,
            'department' => trim((string) ($inner['department'] ?? '')) ?: null,
            'priority' => trim((string) ($inner['priority'] ?? 'normal')) ?: 'normal',
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

        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        if ($lines !== []) {
            $total = LineItems::syncPurchaseRequestItems($id, $lines);
            $model->update($id, ['total_estimated' => $total]);
        }

        return ['ok' => true, 'purchase_request_id' => $id, 'status' => 'draft', 'draft' => true];
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
     * @param array<string, mixed> $queueRow
     * @param array<string, mixed> $inner
     * @return array{company_id: int, branch_id: int, user_id: int, device_id: string}
     */
    private function buildScope(array $queueRow, array $inner): array
    {
        return [
            'company_id' => (int) ($queueRow['company_id'] ?? $inner['company_id'] ?? 0),
            'branch_id' => (int) ($queueRow['branch_id'] ?? $inner['branch_id'] ?? 0),
            'user_id' => (int) ($queueRow['user_id'] ?? $inner['user_id'] ?? 0),
            'device_id' => (string) ($queueRow['device_id'] ?? $inner['device_id'] ?? ''),
        ];
    }
}
