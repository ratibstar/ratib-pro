<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\Warehouse;

/**
 * Tenant + branch isolation checks for inventory offline replay.
 * Additive — does not alter Inventory/Warehouse business services.
 */
final class InventoryOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope company_id, branch_id
     * @return array{ok: bool, error?: string, item?: array<string, mixed>}
     */
    public function assertInventory(int $inventoryId, array $scope): array
    {
        if ($inventoryId < 1) {
            return ['ok' => false, 'error' => 'invalid_inventory_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }

        $item = (new Inventory())->find($inventoryId);
        if ($item === null) {
            return ['ok' => false, 'error' => 'inventory_not_found'];
        }
        if ((int) ($item['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }

        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($item['branch_id']) && $item['branch_id'] !== null && $item['branch_id'] !== '') {
            if ((int) $item['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'item' => $item];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string}
     */
    public function assertWarehouse(?int $warehouseId, array $scope): array
    {
        if ($warehouseId === null || $warehouseId < 1) {
            return ['ok' => true];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }

        $row = (new Warehouse())->find($warehouseId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'warehouse_not_found'];
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'warehouse_tenant_mismatch'];
        }

        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'warehouse_branch_mismatch'];
            }
        }

        return ['ok' => true];
    }

    public function movementExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !Database::liveTableHasColumn('rateb_stock_movements', 'id')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_stock_movements
             WHERE company_id = :cid AND notes LIKE :marker
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'marker' => $marker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    public function transferExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !Database::liveTableHasColumn('rateb_warehouse_transfers', 'id')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_warehouse_transfers
             WHERE company_id = :cid AND notes LIKE :marker
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'marker' => $marker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    public function auditExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !Database::liveTableHasColumn('rateb_inventory_audits', 'id')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_inventory_audits
             WHERE company_id = :cid AND notes LIKE :marker
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'marker' => $marker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) ($row['id'] ?? 0) : null;
    }
}
