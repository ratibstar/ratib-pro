<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\BiAlert;
use Rateb\App\Models\BiAnalyticsScope;
use Rateb\App\Models\BiDashboard;
use Rateb\App\Models\BiDataset;
use Rateb\App\Models\BiDatasetLink;
use Rateb\App\Models\BiDrilldown;
use Rateb\App\Models\BiExport;
use Rateb\App\Models\BiForecast;
use Rateb\App\Models\BiKpi;
use Rateb\App\Models\BiKpiSnapshot;
use Rateb\App\Models\BiReport;
use Rateb\App\Models\BiSchedule;
use Rateb\App\Models\BiTrend;
use Rateb\App\Models\BiWidget;

/**
 * Phase 27A — Enterprise Business Intelligence Platform domain services (ONLINE).
 * Controllers call these only. workflow_status via BusinessIntelligenceWorkflowService only.
 * Soft-links source modules — never mutates CRM/Projects/Accounting/HR/etc.
 */

final class BiEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $out = [];
        $maps = [
            BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD => [
                'table' => 'rateb_bi_dashboards',
                'model' => new BiDashboard(),
            ],
            BusinessIntelligenceWorkflowService::ENTITY_REPORT => [
                'table' => 'rateb_bi_reports',
                'model' => new BiReport(),
            ],
            BusinessIntelligenceWorkflowService::ENTITY_KPI => [
                'table' => 'rateb_bi_kpis',
                'model' => new BiKpi(),
            ],
        ];
        foreach ($maps as $entityType => $cfg) {
            $counts = [];
            foreach (BusinessIntelligenceWorkflowService::statuses($entityType) as $st) {
                $row = $cfg['model']->queryOne(
                    'SELECT COUNT(*) AS c FROM ' . $cfg['table']
                    . ' WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                    ['cid' => $companyId, 'st' => $st]
                );
                $counts[$st] = (int) ($row['c'] ?? 0);
            }
            $out[$entityType] = $counts;
        }

        return $out;
    }
}

final class BiDashboardService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new BiDashboard())->queryOne('SELECT COUNT(*) AS c FROM rateb_bi_dashboards WHERE ' . $where, $params);
        $items = (new BiDashboard())->query(
            'SELECT * FROM rateb_bi_dashboards WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return BusinessIntelligenceSupport::findDashboard($id, BusinessIntelligenceSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_dashboards', 'BI-DASH', $companyId);
        }
        $type = (string) ($input['dashboard_type'] ?? 'custom');
        if (!in_array($type, ['executive', 'department', 'branch', 'company', 'custom'], true)) {
            $type = 'custom';
        }
        $id = (new BiDashboard())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => BusinessIntelligenceSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => BusinessIntelligenceSupport::nullIfEmpty($input['description'] ?? null),
            'dashboard_type' => $type,
            'owner_user_id' => BusinessIntelligenceSupport::intOrNull($input['owner_user_id'] ?? null)
                ?? BusinessIntelligenceSupport::userId(),
            'layout_json' => BusinessIntelligenceSupport::nullIfEmpty($input['layout_json'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('dashboard_created', 'Dashboard: ' . $name, 'dashboard', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiWidgetService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $dashboardId = null): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($dashboardId !== null && $dashboardId > 0) {
            $where .= ' AND dashboard_id = :did';
            $params['did'] = $dashboardId;
        }
        $totalRow = (new BiWidget())->queryOne('SELECT COUNT(*) AS c FROM rateb_bi_widgets WHERE ' . $where, $params);
        $items = (new BiWidget())->query(
            'SELECT * FROM rateb_bi_widgets WHERE ' . $where
            . ' ORDER BY sort_order ASC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $dashboardId = BusinessIntelligenceSupport::intOrNull($input['dashboard_id'] ?? null);
        if ($dashboardId === null) {
            throw new \InvalidArgumentException('dashboard_required');
        }
        BusinessIntelligenceSupport::assertDashboard($dashboardId, $companyId);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_widgets', 'BI-WID', $companyId);
        }
        $wtype = (string) ($input['widget_type'] ?? 'kpi');
        if (!in_array($wtype, ['kpi', 'chart', 'table', 'list', 'gauge', 'text'], true)) {
            $wtype = 'kpi';
        }
        $id = (new BiWidget())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'dashboard_id' => $dashboardId,
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'title_ar' => BusinessIntelligenceSupport::nullIfEmpty($input['title_ar'] ?? null),
            'widget_type' => $wtype,
            'data_source' => BusinessIntelligenceSupport::nullIfEmpty($input['data_source'] ?? null),
            'config_json' => BusinessIntelligenceSupport::nullIfEmpty($input['config_json'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('widget_created', 'Widget: ' . $title, 'widget', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiKpiService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2 OR metric_key LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $totalRow = (new BiKpi())->queryOne('SELECT COUNT(*) AS c FROM rateb_bi_kpis WHERE ' . $where, $params);
        $items = (new BiKpi())->query(
            'SELECT * FROM rateb_bi_kpis WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return BusinessIntelligenceSupport::findKpi($id, BusinessIntelligenceSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $metricKey = trim((string) ($input['metric_key'] ?? ''));
        if ($metricKey === '') {
            throw new \InvalidArgumentException('metric_key_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_kpis', 'BI-KPI', $companyId);
        }
        $source = BusinessIntelligenceSupport::normalizeSourceModule($input['source_module'] ?? null);
        $id = (new BiKpi())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => BusinessIntelligenceSupport::nullIfEmpty($input['name_ar'] ?? null),
            'metric_key' => substr($metricKey, 0, 80),
            'unit' => BusinessIntelligenceSupport::nullIfEmpty($input['unit'] ?? null),
            'target_value' => isset($input['target_value']) && $input['target_value'] !== ''
                ? (float) $input['target_value'] : null,
            'direction' => in_array((string) ($input['direction'] ?? 'higher_better'), ['higher_better', 'lower_better', 'neutral'], true)
                ? (string) $input['direction'] : 'higher_better',
            'source_module' => $source,
            'formula_text' => BusinessIntelligenceSupport::nullIfEmpty($input['formula_text'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('kpi_created', 'KPI: ' . $name, 'kpi', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function recordSnapshot(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $kpiId = BusinessIntelligenceSupport::intOrNull($input['kpi_id'] ?? null);
        if ($kpiId === null) {
            throw new \InvalidArgumentException('kpi_required');
        }
        BusinessIntelligenceSupport::assertKpi($kpiId, $companyId);
        $id = (new BiKpiSnapshot())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'kpi_id' => $kpiId,
            'snapshot_at' => date('Y-m-d H:i:s'),
            'metric_value' => (float) ($input['metric_value'] ?? 0),
            'period_key' => BusinessIntelligenceSupport::nullIfEmpty($input['period_key'] ?? null),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class BiReportService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $reportType = null): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        if ($reportType !== null && $reportType !== '') {
            $where .= ' AND report_type = :rt';
            $params['rt'] = $reportType;
        }
        $totalRow = (new BiReport())->queryOne('SELECT COUNT(*) AS c FROM rateb_bi_reports WHERE ' . $where, $params);
        $items = (new BiReport())->query(
            'SELECT * FROM rateb_bi_reports WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return BusinessIntelligenceSupport::findReport($id, BusinessIntelligenceSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_reports', 'BI-RPT', $companyId);
        }
        $rtype = (string) ($input['report_type'] ?? 'saved');
        if (!in_array($rtype, ['saved', 'drilldown', 'department', 'branch', 'company', 'cross_module', 'trend'], true)) {
            $rtype = 'saved';
        }
        $source = BusinessIntelligenceSupport::normalizeSourceModule($input['source_module'] ?? null);
        $id = (new BiReport())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => BusinessIntelligenceSupport::nullIfEmpty($input['name_ar'] ?? null),
            'report_type' => $rtype,
            'source_module' => $source,
            'query_meta_json' => BusinessIntelligenceSupport::nullIfEmpty($input['query_meta_json'] ?? null),
            'filters_json' => BusinessIntelligenceSupport::nullIfEmpty($input['filters_json'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('report_created', 'Report: ' . $name, 'report', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiDatasetService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiDataset())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_datasets WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiDataset())->query(
            'SELECT * FROM rateb_bi_datasets WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $source = BusinessIntelligenceSupport::normalizeSourceModule($input['source_module'] ?? null);
        if ($source === null) {
            throw new \InvalidArgumentException('source_module_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_datasets', 'BI-DS', $companyId);
        }
        $id = (new BiDataset())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => BusinessIntelligenceSupport::nullIfEmpty($input['name_ar'] ?? null),
            'source_module' => $source,
            'entity_hint' => BusinessIntelligenceSupport::nullIfEmpty($input['entity_hint'] ?? null),
            'refresh_mode' => in_array((string) ($input['refresh_mode'] ?? 'manual'), ['manual', 'scheduled', 'on_demand'], true)
                ? (string) $input['refresh_mode'] : 'manual',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        $linkedId = BusinessIntelligenceSupport::intOrNull($input['linked_entity_id'] ?? null);
        $linkedType = trim((string) ($input['linked_entity_type'] ?? ''));
        if ($linkedId !== null && $linkedType !== '') {
            (new BiDatasetLink())->create(array_merge([
                'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
                'company_id' => $companyId,
                'branch_id' => BusinessIntelligenceSupport::branchId(),
                'dataset_id' => (int) $id,
                'linked_module' => $source,
                'linked_entity_type' => substr($linkedType, 0, 60),
                'linked_entity_id' => $linkedId,
                'link_role' => BusinessIntelligenceSupport::nullIfEmpty($input['link_role'] ?? null),
                'status' => 'active',
                'version' => 1,
            ], BusinessIntelligenceSupport::actorFields(true)));
        }

        (new BiTimelineService())->record('dataset_created', 'Dataset: ' . $name, 'dataset', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiAlertService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiAlert())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_alerts WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiAlert())->query(
            'SELECT * FROM rateb_bi_alerts WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_alerts', 'BI-AL', $companyId);
        }
        $id = (new BiAlert())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'kpi_id' => BusinessIntelligenceSupport::intOrNull($input['kpi_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'threshold_value' => isset($input['threshold_value']) && $input['threshold_value'] !== ''
                ? (float) $input['threshold_value'] : null,
            'comparison' => in_array((string) ($input['comparison'] ?? 'gt'), ['gt', 'gte', 'lt', 'lte', 'eq'], true)
                ? (string) $input['comparison'] : 'gt',
            'alert_status' => 'active',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('alert_created', 'Alert: ' . $name, 'alert', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiScheduleService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiSchedule())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_schedules WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiSchedule())->query(
            'SELECT * FROM rateb_bi_schedules WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_schedules', 'BI-SCH', $companyId);
        }
        $id = (new BiSchedule())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'report_id' => BusinessIntelligenceSupport::intOrNull($input['report_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'cron_hint' => BusinessIntelligenceSupport::nullIfEmpty($input['cron_hint'] ?? null),
            'next_run_at' => BusinessIntelligenceSupport::nullIfEmpty($input['next_run_at'] ?? null),
            'schedule_status' => 'active',
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('schedule_created', 'Schedule: ' . $name, 'schedule', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiExportService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiExport())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_exports WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiExport())->query(
            'SELECT * FROM rateb_bi_exports WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * Metadata-only export request (no binary generation in 27A).
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $reportId = BusinessIntelligenceSupport::intOrNull($input['report_id'] ?? null);
        if ($reportId === null) {
            throw new \InvalidArgumentException('report_required');
        }
        BusinessIntelligenceSupport::assertReport($reportId, $companyId);
        $fmt = (string) ($input['export_format'] ?? 'csv');
        if (!in_array($fmt, ['csv', 'xlsx', 'pdf', 'json'], true)) {
            $fmt = 'csv';
        }
        $id = (new BiExport())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'report_id' => $reportId,
            'export_format' => $fmt,
            'export_status' => 'pending',
            'requested_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('export_requested', 'Export #' . $id, 'export', (int) $id);

        return ['id' => (int) $id];
    }
}

final class BiTrendService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiTrend())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_trends WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiTrend())->query(
            'SELECT * FROM rateb_bi_trends WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_trends', 'BI-TR', $companyId);
        }
        $id = (new BiTrend())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'kpi_id' => BusinessIntelligenceSupport::intOrNull($input['kpi_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'period_grain' => in_array((string) ($input['period_grain'] ?? 'month'), ['day', 'week', 'month', 'quarter', 'year'], true)
                ? (string) $input['period_grain'] : 'month',
            'series_json' => BusinessIntelligenceSupport::nullIfEmpty($input['series_json'] ?? null),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('trend_created', 'Trend: ' . $name, 'trend', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiForecastService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new BiForecast())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_forecasts WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new BiForecast())->query(
            'SELECT * FROM rateb_bi_forecasts WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * Forecast metadata only (no ML engine in 27A).
     *
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_forecasts', 'BI-FC', $companyId);
        }
        $id = (new BiForecast())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'kpi_id' => BusinessIntelligenceSupport::intOrNull($input['kpi_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'horizon_periods' => max(1, (int) ($input['horizon_periods'] ?? 3)),
            'method_hint' => BusinessIntelligenceSupport::nullIfEmpty($input['method_hint'] ?? null),
            'forecast_json' => BusinessIntelligenceSupport::nullIfEmpty($input['forecast_json'] ?? null),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        (new BiTimelineService())->record('forecast_created', 'Forecast: ' . $name, 'forecast', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiAnalyticsScopeService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $scopeType = null): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($scopeType !== null && $scopeType !== '') {
            $where .= ' AND scope_type = :st';
            $params['st'] = $scopeType;
        }
        $totalRow = (new BiAnalyticsScope())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_bi_analytics_scopes WHERE ' . $where,
            $params
        );
        $items = (new BiAnalyticsScope())->query(
            'SELECT * FROM rateb_bi_analytics_scopes WHERE ' . $where
            . ' ORDER BY name ASC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = BusinessIntelligenceSupport::nextCode('rateb_bi_analytics_scopes', 'BI-SC', $companyId);
        }
        $stype = (string) ($input['scope_type'] ?? 'company');
        if (!in_array($stype, ['company', 'branch', 'department'], true)) {
            $stype = 'company';
        }
        $id = (new BiAnalyticsScope())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'scope_type' => $stype,
            'scope_ref_id' => BusinessIntelligenceSupport::intOrNull($input['scope_ref_id'] ?? null),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BiDrilldownService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = BusinessIntelligenceSupport::requireCompanyId();
        $reportId = BusinessIntelligenceSupport::intOrNull($input['report_id'] ?? null);
        if ($reportId === null) {
            throw new \InvalidArgumentException('report_required');
        }
        BusinessIntelligenceSupport::assertReport($reportId, $companyId);
        $parent = trim((string) ($input['parent_level'] ?? ''));
        $child = trim((string) ($input['child_level'] ?? ''));
        if ($parent === '' || $child === '') {
            throw new \InvalidArgumentException('drilldown_levels_required');
        }
        $id = (new BiDrilldown())->create(array_merge([
            'public_uuid' => BusinessIntelligenceSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => BusinessIntelligenceSupport::branchId(),
            'report_id' => $reportId,
            'parent_level' => substr($parent, 0, 60),
            'child_level' => substr($child, 0, 60),
            'config_json' => BusinessIntelligenceSupport::nullIfEmpty($input['config_json'] ?? null),
            'status' => 'active',
            'notes' => BusinessIntelligenceSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], BusinessIntelligenceSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
