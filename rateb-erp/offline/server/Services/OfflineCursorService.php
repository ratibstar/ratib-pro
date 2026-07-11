<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/** Delta cursor registry — inventory catalog + employee directory when flags on. */
final class OfflineCursorService
{
    private ?OfflineEntityCursor $model = null;
    private ?InventoryOfflineCatalogService $catalog = null;
    private ?HrOfflineEmployeeDirectoryService $employees = null;

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

    public function isAvailable(): bool
    {
        return OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id');
    }

    /** @return array<string, mixed> */
    public function getCursor(string $entityType, ?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null): array
    {
        $entityType = trim($entityType);
        if ($entityType === '') {
            return ['entity_type' => $entityType, 'cursor_token' => null, 'items' => [], 'stub' => true];
        }

        if (in_array($entityType, ['inventory_catalog', 'inventory', 'catalog'], true)) {
            $token = $cursorToken;
            if ($token === null || $token === '') {
                $token = $this->readStoredToken('inventory_catalog', $companyId, $branchId);
            }

            return $this->catalog()->pull($companyId, $branchId, $token);
        }

        if (in_array($entityType, ['employee_directory', 'employees', 'hr_employees'], true)) {
            $token = $cursorToken;
            if ($token === null || $token === '') {
                $token = $this->readStoredToken('employee_directory', $companyId, $branchId);
            }

            return $this->employees()->pull($companyId, $branchId, $token);
        }

        if (!$this->isAvailable()) {
            return ['entity_type' => $entityType, 'cursor_token' => null, 'stub' => true, 'items' => []];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return ['entity_type' => $entityType, 'cursor_token' => null, 'items' => []];
        }

        $params = [
            'cid' => $companyId,
            'et' => substr($entityType, 0, 64),
        ];
        $sql = 'SELECT * FROM rateb_offline_entity_cursors
                WHERE company_id = :cid AND entity_type = :et';
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';

        $row = $this->model()->queryOne($sql, $params);

        return [
            'entity_type' => $entityType,
            'cursor_token' => $row['cursor_token'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'items' => [],
            'stub' => true,
        ];
    }

    private function readStoredToken(string $entityType, ?int $companyId, ?int $branchId): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return null;
        }
        $params = ['cid' => $companyId, 'et' => $entityType];
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

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
