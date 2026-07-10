<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/** Delta cursor registry — Phase 2A foundation (no entity pull yet). */
final class OfflineCursorService
{
    private ?OfflineEntityCursor $model = null;

    private function model(): OfflineEntityCursor
    {
        return $this->model ??= new OfflineEntityCursor();
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_offline_entity_cursors', 'id');
    }

    /** @return array<string, mixed> */
    public function getCursor(string $entityType, ?int $companyId = null, ?int $branchId = null): array
    {
        if (!$this->isAvailable() || $entityType === '') {
            return ['entity_type' => $entityType, 'cursor_token' => null, 'stub' => true];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return ['entity_type' => $entityType, 'cursor_token' => null];
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

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
