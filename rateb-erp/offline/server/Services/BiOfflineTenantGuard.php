<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\BiDashboard;
use Rateb\App\Models\BiKpi;
use Rateb\App\Models\BiReport;

/**
 * Tenant + branch isolation for BI offline replay (Phase 27B).
 * Additive — does not alter Phase 27A BI domain services.
 */
final class BiOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, dashboard?: array<string, mixed>}
     */
    public function assertDashboard(int $dashboardId, array $scope): array
    {
        return $this->assertRow(
            $dashboardId,
            $scope,
            'rateb_bi_dashboards',
            new BiDashboard(),
            'dashboard_not_found',
            'invalid_dashboard_id',
            'dashboard'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, report?: array<string, mixed>}
     */
    public function assertReport(int $reportId, array $scope): array
    {
        return $this->assertRow(
            $reportId,
            $scope,
            'rateb_bi_reports',
            new BiReport(),
            'report_not_found',
            'invalid_report_id',
            'report'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, kpi?: array<string, mixed>}
     */
    public function assertKpi(int $kpiId, array $scope): array
    {
        return $this->assertRow(
            $kpiId,
            $scope,
            'rateb_bi_kpis',
            new BiKpi(),
            'kpi_not_found',
            'invalid_kpi_id',
            'kpi'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, dashboard?: array<string, mixed>, report?: array<string, mixed>, kpi?: array<string, mixed>}
     */
    private function assertRow(
        int $id,
        array $scope,
        string $table,
        object $model,
        string $notFound,
        string $invalidId,
        string $key
    ): array {
        if ($id < 1) {
            return ['ok' => false, 'error' => $invalidId];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        /** @var array<string, mixed>|null $row */
        $row = $model->queryOne(
            'SELECT * FROM ' . $table . ' WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => $notFound];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, $key => $row];
    }
}
