<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase 13 — Warehouses master-data delta.
 * Requires rateb_warehouses.updated_at (see offline/migrations/004_*).
 */
final class WarehouseOfflineDirectoryService extends AbstractMasterDataDirectoryService
{
    protected function entityType(): string
    {
        return 'warehouse_directory';
    }

    protected function table(): string
    {
        return 'rateb_warehouses';
    }

    protected function branchColumn(): ?string
    {
        return 'branch_id';
    }

    protected function selectColumns(): array
    {
        $cols = ['id', 'company_id', 'name', 'code', 'location', 'manager_name', 'status'];
        if (OfflineSchema::hasColumn($this->table(), 'branch_id')) {
            $cols[] = 'branch_id';
        }

        return $cols;
    }

    protected function mapItem(array $row): array
    {
        $status = (string) ($row['status'] ?? '');
        $deleted = OfflineDeltaCursorCodec::isInactiveStatus($status);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'manager_name' => (string) ($row['manager_name'] ?? ''),
            'status' => $status,
            'active' => !$deleted,
            'deleted' => $deleted,
            'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
            'version' => max(1, (int) ($row['id'] ?? 1)),
        ];
    }
}
