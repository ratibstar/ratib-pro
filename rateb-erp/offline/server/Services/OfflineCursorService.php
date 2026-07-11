<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Delta cursor registry — Tier-1 catalogs + Phase 13 master-data directories.
 * Phase 13.1: client-supplied cursor is authoritative; server token is never
 * injected into pulls (avoids multi-device skip). Server persist remains telemetry.
 */
final class OfflineCursorService
{
    private ?OfflineEntityCursor $model = null;
    private ?InventoryOfflineCatalogService $catalog = null;
    private ?HrOfflineEmployeeDirectoryService $employees = null;
    private ?ProcurementOfflineSupplierDirectoryService $suppliers = null;
    private ?CustomerOfflineDirectoryService $customers = null;
    private ?BranchOfflineDirectoryService $branches = null;
    private ?WarehouseOfflineDirectoryService $warehouses = null;

    private function model(): OfflineEntityCursor
    {
        return $this->model ??= new OfflineEntityCursor();
    }

    private function catalog(): InventoryOfflineCatalogService
    {
        return $this->catalog ??= new InventoryOfflineCatalogService();
    }

    private function employees(): HrOfflineEmployeeDirectoryService
    {
        return $this->employees ??= new HrOfflineEmployeeDirectoryService();
    }

    private function suppliers(): ProcurementOfflineSupplierDirectoryService
    {
        return $this->suppliers ??= new ProcurementOfflineSupplierDirectoryService();
    }

    private function customers(): CustomerOfflineDirectoryService
    {
        return $this->customers ??= new CustomerOfflineDirectoryService();
    }

    private function branches(): BranchOfflineDirectoryService
    {
        return $this->branches ??= new BranchOfflineDirectoryService();
    }

    private function warehouses(): WarehouseOfflineDirectoryService
    {
        return $this->warehouses ??= new WarehouseOfflineDirectoryService();
    }

    public function isAvailable(): bool
    {
        return OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id');
    }

    /** @return array<string, mixed> */
    public function getCursor(string $entityType, ?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null): array
    {
        $entityType = trim($entityType);
        if ($entityType === '') {
            return [
                'entity_type' => $entityType,
                'cursor_token' => null,
                'items' => [],
                'error' => 'entity_required',
            ];
        }

        $policy = new ErpOfflineMasterDataPolicy();
        $masterCanonical = $policy->resolveCanonical($entityType);
        $isLegacy = $policy->isLegacyTier1Entity($entityType);

        if ($masterCanonical === null && !$isLegacy) {
            return [
                'entity_type' => $entityType,
                'cursor_token' => null,
                'items' => [],
                'error' => 'entity_not_allowed',
                'stub' => false,
            ];
        }

        // Client-owned cursor: empty/null means bootstrap from start — never inject server token.
        $token = ($cursorToken !== null && $cursorToken !== '') ? $cursorToken : null;

        if (in_array($entityType, ['inventory_catalog', 'inventory', 'catalog'], true)) {
            return $this->catalog()->pull($companyId, $branchId, $token);
        }

        if (in_array($entityType, ['employee_directory', 'employees', 'hr_employees'], true)
            || $masterCanonical === 'employee_directory') {
            return $this->employees()->pull($companyId, $branchId, $token);
        }

        if (in_array($entityType, ['supplier_directory', 'suppliers', 'procurement_suppliers'], true)
            || $masterCanonical === 'supplier_directory') {
            return $this->suppliers()->pull($companyId, $branchId, $token);
        }

        if ($masterCanonical === 'customer_directory') {
            return $this->customers()->pull($companyId, $branchId, $token);
        }

        if ($masterCanonical === 'branch_directory') {
            return $this->branches()->pull($companyId, $branchId, $token);
        }

        if ($masterCanonical === 'warehouse_directory') {
            return $this->warehouses()->pull($companyId, $branchId, $token);
        }

        return [
            'entity_type' => $entityType,
            'cursor_token' => null,
            'items' => [],
            'error' => 'entity_not_allowed',
        ];
    }

    /**
     * Optional telemetry read — not used to drive pull authority (Phase 13.1).
     */
    public function peekStoredToken(string $entityType, ?int $companyId, ?int $branchId): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        $companyId = $companyId ?? (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1) {
            return null;
        }
        $params = ['cid' => $companyId, 'et' => substr($entityType, 0, 64)];
        $sql = 'SELECT cursor_token FROM rateb_offline_entity_cursors
                WHERE company_id = :cid AND entity_type = :et';
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = $this->model()->queryOne($sql, $params);

        return isset($row['cursor_token']) ? (string) $row['cursor_token'] : null;
    }
}
