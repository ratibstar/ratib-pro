<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\InventoryWorkflowService;
use Rateb\App\Services\StockMovementService;

/**
 * Thin inventory offline replay adapter — delegates to existing inventory services only.
 * No business logic duplication (Phase 3 / Tier 1).
 */
final class InventoryOfflineReplayService
{
    /**
     * Actions that invoke inventory domain services.
     *
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'stock_movement.create',
            'stock_movement',
            'inventory.stock_movement',
            'stock_count.create',
            'stock_count',
            'inventory.stock_count',
            'warehouse_transfer.create',
            'warehouse_transfer',
            'inventory.warehouse_transfer',
            'warehouse_transfer.approve',
        ];
    }

    public function __construct(
        private ?InventoryOfflineTenantGuard $guard = null,
        private ?StockMovementService $movements = null,
        private ?InventoryWorkflowService $workflow = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): InventoryOfflineTenantGuard
    {
        return $this->guard ??= new InventoryOfflineTenantGuard();
    }

    private function movements(): StockMovementService
    {
        return $this->movements ??= new StockMovementService();
    }

    private function workflow(): InventoryWorkflowService
    {
        return $this->workflow ??= new InventoryWorkflowService();
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
            return ['status' => 'skipped', 'error' => 'unknown_inventory_action'];
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

        return match ($action) {
            'stock_movement.create', 'stock_movement', 'inventory.stock_movement'
                => $this->stockMovement($scope, $inner, $idempotencyKey),
            'stock_count.create', 'stock_count', 'inventory.stock_count'
                => $this->stockCount($scope, $inner, $idempotencyKey),
            'warehouse_transfer.create', 'warehouse_transfer', 'inventory.warehouse_transfer'
                => $this->warehouseTransfer($scope, $inner, $idempotencyKey),
            'warehouse_transfer.approve'
                => $this->approveTransfer($scope, $inner),
            default => throw new \RuntimeException('unknown_inventory_action'),
        };
    }

    /**
     * Version / expected-qty conflict check (server-authoritative).
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveConflict(array $clientItem, ?array $serverItem): array
    {
        $base = $this->resolver()->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }

        $expected = $clientItem['expected_quantity'] ?? ($clientItem['payload']['expected_quantity'] ?? null);
        if ($expected !== null && $serverItem !== null && array_key_exists('quantity', $serverItem)) {
            $serverQty = (float) $serverItem['quantity'];
            $clientExpected = (float) $expected;
            if (abs($serverQty - $clientExpected) > 0.0001) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'quantity_changed',
                ];
            }
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function stockMovement(array $scope, array $inner, string $idempotencyKey): array
    {
        $inventoryId = (int) ($inner['inventory_id'] ?? 0);
        $assert = $this->guard()->assertInventory($inventoryId, $scope);
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

        $item = $assert['item'] ?? [];
        if (isset($inner['expected_quantity'])) {
            $decision = $this->resolver()->resolveInventory(
                ['version' => (int) ($inner['version'] ?? 1), 'expected_quantity' => $inner['expected_quantity']],
                ['version' => (int) ($inner['server_version'] ?? 0), 'quantity' => $item['quantity'] ?? 0]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'quantity_changed'));
            }
        }

        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->movementExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'movement_id' => $existingId];
            }
        }

        $movementType = trim((string) ($inner['movement_type'] ?? 'in'));
        if (!in_array($movementType, ['in', 'out', 'adjustment', 'transfer'], true)) {
            throw new \RuntimeException('invalid_movement_type');
        }
        $quantity = (float) ($inner['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new \RuntimeException('empty_stock_movement_payload');
        }

        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $movementId = $this->movements()->record([
            'inventory_id' => $inventoryId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference_type' => $inner['reference_type'] ?? 'offline_sync',
            'reference_id' => isset($inner['reference_id']) ? (int) $inner['reference_id'] : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return ['ok' => true, 'movement_id' => $movementId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function stockCount(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->auditExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'audit_id' => $existingId];
            }
        }

        $warehouseId = isset($inner['warehouse_id']) ? (int) $inner['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId < 1) {
            $warehouseId = null;
        }
        $wh = $this->guard()->assertWarehouse($warehouseId, $scope);
        if (!$wh['ok']) {
            throw new \RuntimeException((string) ($wh['error'] ?? 'warehouse_tenant_mismatch'));
        }

        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        if ($lines === []) {
            throw new \RuntimeException('empty_stock_count_payload');
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $invId = (int) ($line['inventory_id'] ?? 0);
            $assert = $this->guard()->assertInventory($invId, $scope);
            if (!$assert['ok']) {
                throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
            }
        }

        $auditNo = trim((string) ($inner['audit_no'] ?? ''));
        if ($auditNo === '') {
            $auditNo = $this->workflow()->nextAuditNo();
        }
        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $auditId = $this->workflow()->createAudit($auditNo, $warehouseId, $notes);
        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $normalized[] = [
                'inventory_id' => (int) ($line['inventory_id'] ?? 0),
                'counted_qty' => (float) ($line['counted_qty'] ?? 0),
            ];
        }
        $this->workflow()->saveAuditLines($auditId, $normalized);

        $complete = !empty($inner['complete']) || !empty($inner['reconcile']);
        $adjusted = 0;
        if ($complete) {
            $adjusted = $this->workflow()->completeAudit($auditId);
        }

        return ['ok' => true, 'audit_id' => $auditId, 'adjusted' => $adjusted, 'completed' => $complete];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function warehouseTransfer(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existingId = $this->guard()->transferExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existingId !== null && $existingId > 0) {
                return ['ok' => true, 'idempotent' => true, 'transfer_id' => $existingId];
            }
        }

        $inventoryId = (int) ($inner['inventory_id'] ?? 0);
        $assert = $this->guard()->assertInventory($inventoryId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }

        $src = (int) ($inner['source_warehouse_id'] ?? 0);
        $dst = (int) ($inner['destination_warehouse_id'] ?? 0);
        foreach ([$src, $dst] as $wid) {
            $wh = $this->guard()->assertWarehouse($wid, $scope);
            if (!$wh['ok']) {
                throw new \RuntimeException((string) ($wh['error'] ?? 'warehouse_tenant_mismatch'));
            }
        }

        $qty = (float) ($inner['quantity'] ?? 0);
        if ($src < 1 || $dst < 1 || $src === $dst || $qty <= 0) {
            throw new \RuntimeException('empty_warehouse_transfer_payload');
        }

        $notes = trim((string) ($inner['notes'] ?? ''));
        if ($idempotencyKey !== '') {
            $notes = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }

        $transferId = $this->workflow()->createTransfer([
            'source_warehouse_id' => $src,
            'destination_warehouse_id' => $dst,
            'inventory_id' => $inventoryId,
            'quantity' => $qty,
            'notes' => $notes,
        ]);

        $autoApprove = !empty($inner['approve']) || !empty($inner['auto_approve']);
        if ($autoApprove) {
            $ok = $this->workflow()->approveTransfer($transferId);
            if (!$ok) {
                throw new \RuntimeException('warehouse_transfer_approve_failed');
            }
        }

        return ['ok' => true, 'transfer_id' => $transferId, 'approved' => $autoApprove];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function approveTransfer(array $scope, array $inner): array
    {
        $transferId = (int) ($inner['transfer_id'] ?? 0);
        if ($transferId < 1) {
            throw new \RuntimeException('missing_transfer_id');
        }
        TenantContext::setCompanyId($scope['company_id']);
        $ok = $this->workflow()->approveTransfer($transferId);
        if (!$ok) {
            throw new \RuntimeException('warehouse_transfer_already_processed');
        }

        return ['ok' => true, 'transfer_id' => $transferId, 'approved' => true];
    }

    private function isConflictError(string $message): bool
    {
        $codes = [
            'quantity_changed',
            'server_newer',
            'warehouse_transfer_already_processed',
            'branch_mismatch',
            'warehouse_branch_mismatch',
        ];

        return in_array($message, $codes, true);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        $aliases = [
            'create_stock_movement' => 'stock_movement.create',
            'create_stock_count' => 'stock_count.create',
            'create_warehouse_transfer' => 'warehouse_transfer.create',
            'approve_warehouse_transfer' => 'warehouse_transfer.approve',
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
            return [
                'action' => $queueRow['action'] ?? null,
                'payload' => [],
            ];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'action' => $queueRow['action'] ?? null,
                'payload' => [],
            ];
        }

        return $decoded;
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
