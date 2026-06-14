<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;

/** Shared ops for workflow tables: record IDs, delete, bulk delete. */
final class WorkflowTableService
{
    /** @return array<string, array{table:string,no_column:string,prefix:string,entity:string}> */
    public static function entities(): array
    {
        return [
            'asset-maintenance' => [
                'table' => 'rateb_asset_maintenance',
                'no_column' => 'maintenance_no',
                'prefix' => 'AM',
                'entity' => 'asset_maintenance',
            ],
            'asset-assignments' => [
                'table' => 'rateb_asset_assignments',
                'no_column' => 'assignment_no',
                'prefix' => 'AA',
                'entity' => 'asset_assignments',
            ],
            'asset-depreciation' => [
                'table' => 'rateb_asset_depreciation',
                'no_column' => 'depreciation_no',
                'prefix' => 'AD',
                'entity' => 'asset_depreciation',
            ],
            'device-maintenance' => [
                'table' => 'rateb_device_service_history',
                'no_column' => 'service_no',
                'prefix' => 'DS',
                'entity' => 'device_maintenance',
            ],
            'contract-renewals' => [
                'table' => 'rateb_contract_renewals',
                'no_column' => 'renewal_no',
                'prefix' => 'CR',
                'entity' => 'contract_renewals',
            ],
            'device-spare-parts' => [
                'table' => 'rateb_device_spare_parts',
                'no_column' => 'part_no',
                'prefix' => 'SP',
                'entity' => 'device_spare_parts',
            ],
        ];
    }

    /** @return array{table:string,no_column:string,prefix:string,entity:string}|null */
    public static function config(string $slug): ?array
    {
        return self::entities()[$slug] ?? null;
    }

    public function generateRecordNo(string $slug): string
    {
        $cfg = self::config($slug);
        if ($cfg === null) {
            throw new \InvalidArgumentException('Unknown workflow entity: ' . $slug);
        }
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
        $prefix = (string) $cfg['prefix'];
        $table = (string) $cfg['table'];
        $column = (string) $cfg['no_column'];
        $db = Database::connection();
        $startPos = strlen($prefix) + 1;
        $sql = sprintf(
            'SELECT MAX(CAST(SUBSTRING(%s, %d) AS UNSIGNED)) AS m FROM %s WHERE company_id = :cid AND %s LIKE :like',
            $column,
            $startPos,
            $table,
            $column
        );
        $stmt = $db->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'like' => $prefix . '%']);
        $row = $stmt->fetch();
        $next = (int) ($row['m'] ?? 0) + 1;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix . str_pad((string) ($next + $attempt), 4, '0', STR_PAD_LEFT);
            $check = $db->prepare(sprintf(
                'SELECT id FROM %s WHERE company_id = :cid AND %s = :no LIMIT 1',
                $table,
                $column
            ));
            $check->execute(['cid' => $companyId, 'no' => $candidate]);
            if (!$check->fetch()) {
                return $candidate;
            }
        }
        return $prefix . str_pad((string) ($next + 10), 4, '0', STR_PAD_LEFT);
    }

    public function deleteOne(string $slug, int $id): bool
    {
        $cfg = self::config($slug);
        if ($cfg === null || $id < 1) {
            return false;
        }
        $companyId = $this->resolveCompanyId();
        $db = Database::connection();
        $sql = sprintf('DELETE FROM %s WHERE id = :id', (string) $cfg['table']);
        $params = ['id' => $id];
        if ($companyId > 0 && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        return $db->prepare($sql)->execute($params);
    }

    /** @param array<int, int> $ids */
    public function bulkDelete(string $slug, array $ids): int
    {
        $cfg = self::config($slug);
        if ($cfg === null || $ids === []) {
            return 0;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return 0;
        }
        $companyId = $this->resolveCompanyId();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf('DELETE FROM %s WHERE id IN (%s)', (string) $cfg['table'], $placeholders);
        $params = $ids;
        if ($companyId > 0 && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND company_id = ?';
            $params[] = $companyId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function resolveCompanyId(): int
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        return $companyId;
    }
}
