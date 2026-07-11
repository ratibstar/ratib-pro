<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\BiDashboard;
use Rateb\App\Models\BiKpi;
use Rateb\App\Models\BiReport;

/**
 * Shared helpers for Phase 27A Enterprise BI domain services.
 * Soft-links source modules only — never mutates CRM/Projects/Accounting/etc.
 */
final class BusinessIntelligenceSupport
{
    /** @return list<string> */
    public static function softLinkModules(): array
    {
        return [
            'crm', 'projects', 'accounting', 'hr', 'payroll', 'manufacturing',
            'assets', 'procurement', 'quality', 'documents', 'pos', 'inventory', 'recruitment',
        ];
    }

    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function requireCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            throw new \RuntimeException('company_required');
        }

        return $cid;
    }

    public static function branchId(): ?int
    {
        $bid = (int) (SessionManager::get('rateb_branch_id') ?? SessionManager::get('branch_id') ?? 0);

        return $bid > 0 ? $bid : null;
    }

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    /** @return array<string, mixed> */
    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    public static function nextCode(string $table, string $prefix, int $companyId): string
    {
        $allowed = [
            'rateb_bi_dashboards',
            'rateb_bi_widgets',
            'rateb_bi_kpis',
            'rateb_bi_reports',
            'rateb_bi_datasets',
            'rateb_bi_trends',
            'rateb_bi_forecasts',
            'rateb_bi_alerts',
            'rateb_bi_schedules',
            'rateb_bi_analytics_scopes',
            'rateb_bi_tags',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new BiDashboard())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function assertOptimisticVersion(array $row, mixed $expectedVersion): void
    {
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }
        if ((int) $expectedVersion !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
    }

    /** @return array<string, mixed>|null */
    public static function findDashboard(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new BiDashboard())->queryOne(
            'SELECT * FROM rateb_bi_dashboards WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertDashboard(int $id, int $companyId): array
    {
        $row = self::findDashboard($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('dashboard_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findReport(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new BiReport())->queryOne(
            'SELECT * FROM rateb_bi_reports WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertReport(int $id, int $companyId): array
    {
        $row = self::findReport($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('report_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findKpi(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new BiKpi())->queryOne(
            'SELECT * FROM rateb_bi_kpis WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertKpi(int $id, int $companyId): array
    {
        $row = self::findKpi($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('kpi_not_found');
        }

        return $row;
    }

    public static function nullIfEmpty(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && trim($v) === '') {
            return null;
        }

        return $v;
    }

    public static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public static function normalizeSourceModule(mixed $v): ?string
    {
        $m = strtolower(trim((string) ($v ?? '')));
        if ($m === '') {
            return null;
        }
        if (!in_array($m, self::softLinkModules(), true)) {
            throw new \InvalidArgumentException('invalid_source_module');
        }

        return $m;
    }
}
