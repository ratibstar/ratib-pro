<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmSavedDashboard;
use Rateb\App\Models\CrmScheduledReport;

/**
 * Phase 8 — Enterprise reporting center (saved dashboards + scheduled reports, no new email).
 */
final class CrmReportingCenterService
{
    /** @return list<array<string, mixed>> */
    public function listSavedDashboards(?int $userId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $uid = $userId ?? CrmSupport::userId();
        $rows = (new CrmSavedDashboard())->query(
            'SELECT * FROM rateb_crm_saved_dashboards
             WHERE company_id = :cid AND deleted_at IS NULL
               AND (is_shared = 1 OR user_id = :uid OR user_id IS NULL)
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $companyId, 'uid' => $uid ?? 0]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveDashboard(array $data): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('dashboard_name_required');
        }
        $id = (int) (new CrmSavedDashboard())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'user_id' => CrmSupport::userId(),
            'name' => substr($name, 0, 120),
            'role_key' => substr(trim((string) ($data['role_key'] ?? 'executive')), 0, 40),
            'layout_json' => json_encode($data['layout'] ?? ['widgets' => ['revenue', 'forecast', 'quality']], JSON_UNESCAPED_UNICODE),
            'filters_json' => json_encode($data['filters'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_shared' => !empty($data['is_shared']) ? 1 : 0,
        ], CrmSupport::actorFields(true)));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.config', 'crm_saved_dashboard', $id, [
                'name' => $name,
            ]);
        }
        $row = (new CrmSavedDashboard())->queryOne(
            'SELECT * FROM rateb_crm_saved_dashboards WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) ? $row : ['id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function listScheduledReports(): array
    {
        $rows = (new CrmScheduledReport())->query(
            'SELECT * FROM rateb_crm_scheduled_reports
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY next_run_at ASC LIMIT 50',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveScheduledReport(array $data): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('report_name_required');
        }
        $freq = strtolower(trim((string) ($data['frequency'] ?? 'weekly')));
        if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $freq = 'weekly';
        }
        $id = (int) (new CrmScheduledReport())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'user_id' => CrmSupport::userId(),
            'name' => substr($name, 0, 120),
            'report_key' => substr(trim((string) ($data['report_key'] ?? 'funnel')), 0, 60),
            'frequency' => $freq,
            'filters_json' => json_encode($data['filters'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_enabled' => array_key_exists('is_enabled', $data) ? (!empty($data['is_enabled']) ? 1 : 0) : 1,
            'next_run_at' => $this->nextRunAt($freq),
            'last_status' => 'scheduled',
        ], CrmSupport::actorFields(true)));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.config', 'crm_scheduled_report', $id, [
                'frequency' => $freq,
            ]);
        }
        $row = (new CrmScheduledReport())->queryOne(
            'SELECT * FROM rateb_crm_scheduled_reports WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) ? $row : ['id' => $id];
    }

    /**
     * Run due scheduled reports: build export payload + audit (no email provider).
     *
     * @return array{ran:int,items:list<array<string,mixed>>}
     */
    public function runDue(int $limit = 20): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmScheduledReport())->query(
            "SELECT * FROM rateb_crm_scheduled_reports
             WHERE company_id = :cid AND deleted_at IS NULL AND is_enabled = 1
               AND (next_run_at IS NULL OR next_run_at <= NOW())
             ORDER BY next_run_at ASC LIMIT " . max(1, min(50, $limit)),
            ['cid' => $companyId]
        );
        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $filters = json_decode((string) ($row['filters_json'] ?? '{}'), true);
            if (!is_array($filters)) {
                $filters = [];
            }
            $filters['report'] = (string) ($row['report_key'] ?? 'funnel');
            try {
                try {
                    $policy = (new CrmGovernanceService())->validateExportPolicy();
                    if (!$policy['ok']) {
                        throw new \RuntimeException('export_policy_blocked:' . implode(',', $policy['violations']));
                    }
                } catch (\RuntimeException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    // pre-migrate governance settings
                }
                $payload = (new CrmReportExportService())->build($filters);
                (new CrmScheduledReport())->update((int) $row['id'], array_merge([
                    'last_run_at' => date('Y-m-d H:i:s'),
                    'last_status' => 'ok',
                    'next_run_at' => $this->nextRunAt((string) ($row['frequency'] ?? 'weekly')),
                ], CrmSupport::actorFields(false)));
                if (class_exists(AuditService::class)) {
                    (new AuditService())->log('crm.export.csv', 'crm_scheduled_report', (int) $row['id'], [
                        'report_key' => $filters['report'],
                        'rows' => count($payload['rows'] ?? []),
                        'scheduled' => true,
                    ]);
                }
                $items[] = ['id' => (int) $row['id'], 'status' => 'ok', 'rows' => count($payload['rows'] ?? [])];
            } catch (\Throwable $e) {
                (new CrmScheduledReport())->update((int) $row['id'], [
                    'last_run_at' => date('Y-m-d H:i:s'),
                    'last_status' => 'error:' . substr($e->getMessage(), 0, 80),
                    'next_run_at' => $this->nextRunAt((string) ($row['frequency'] ?? 'weekly')),
                ]);
                $items[] = ['id' => (int) $row['id'], 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return ['ran' => count($items), 'items' => $items];
    }

    private function nextRunAt(string $frequency): string
    {
        return match ($frequency) {
            'daily' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'monthly' => date('Y-m-d H:i:s', strtotime('+1 month')),
            default => date('Y-m-d H:i:s', strtotime('+1 week')),
        };
    }
}
