<?php

declare(strict_types=1);

/**
 * Phase 27A — Enterprise Business Intelligence & Analytics Platform (ONLINE) gate tests.
 *
 * Run: php tests/bi/run-business-intelligence-phase27a-tests.php
 */
final class BusinessIntelligencePhase27ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineCoupling();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testSoftLinkOnly();
        $this->testWorkflowMaps();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testSidebarAndLang();
        $this->testArchitectureDoc();
        $this->testOfflineReadinessDoc();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testBaselineUntouched(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Enterprise Baseline / Offline Foundation markers intact', $ok);
    }

    private function testNoOfflineCoupling(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/BusinessIntelligenceDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/BusinessIntelligenceWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.bi')
            && !is_file(RATEB_ROOT . '/offline/server/Services/BiOfflineReplayService.php')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/bi-adapter.js');
        $this->record('27A online layer has no offline coupling (27B deferred)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/193_business_intelligence_platform.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_bi_dashboards')
            && str_contains($sql, 'rateb_bi_widgets')
            && str_contains($sql, 'rateb_bi_kpis')
            && str_contains($sql, 'rateb_bi_kpi_snapshots')
            && str_contains($sql, 'rateb_bi_reports')
            && str_contains($sql, 'rateb_bi_report_runs')
            && str_contains($sql, 'rateb_bi_datasets')
            && str_contains($sql, 'rateb_bi_dataset_links')
            && str_contains($sql, 'rateb_bi_drilldowns')
            && str_contains($sql, 'rateb_bi_trends')
            && str_contains($sql, 'rateb_bi_forecasts')
            && str_contains($sql, 'rateb_bi_alerts')
            && str_contains($sql, 'rateb_bi_schedules')
            && str_contains($sql, 'rateb_bi_exports')
            && str_contains($sql, 'rateb_bi_analytics_scopes')
            && str_contains($sql, 'rateb_bi_comments')
            && str_contains($sql, 'rateb_bi_timeline')
            && str_contains($sql, 'rateb_bi_status_history')
            && str_contains($sql, 'rateb_bi_audit_logs')
            && str_contains($sql, 'rateb_bi_favorites')
            && str_contains($sql, 'rateb_bi_tags')
            && str_contains($sql, 'bi.view')
            && str_contains($sql, 'bi.publish')
            && str_contains($sql, 'bi.export')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_crm_')
            && !str_contains($sql, 'ALTER TABLE rateb_dms_')
            && !str_contains($sql, 'ALTER TABLE rateb_payroll_');
        $this->record('migration 193 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\BusinessIntelligenceSupport::class)
            && class_exists(\Rateb\App\Services\BusinessIntelligenceWorkflowService::class)
            && class_exists(\Rateb\App\Services\BiTimelineService::class)
            && class_exists(\Rateb\App\Services\BiEnterpriseService::class)
            && class_exists(\Rateb\App\Services\BiDashboardService::class)
            && class_exists(\Rateb\App\Services\BiWidgetService::class)
            && class_exists(\Rateb\App\Services\BiKpiService::class)
            && class_exists(\Rateb\App\Services\BiReportService::class)
            && class_exists(\Rateb\App\Services\BiDatasetService::class)
            && class_exists(\Rateb\App\Services\BiAlertService::class)
            && class_exists(\Rateb\App\Services\BiScheduleService::class)
            && class_exists(\Rateb\App\Services\BiExportService::class)
            && class_exists(\Rateb\App\Services\BiTrendService::class)
            && class_exists(\Rateb\App\Services\BiForecastService::class)
            && class_exists(\Rateb\App\Services\BiAnalyticsScopeService::class)
            && class_exists(\Rateb\App\Models\BiDashboard::class)
            && class_exists(\Rateb\App\Models\BiReport::class)
            && class_exists(\Rateb\App\Models\BiKpi::class);
        $this->record('domain services present', $ok);
    }

    private function testSoftLinkOnly(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/BusinessIntelligenceDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/BusinessIntelligenceWorkflowService.php');
        $support = (string) file_get_contents(RATEB_ROOT . '/app/services/BusinessIntelligenceSupport.php');
        $ok = str_contains($domain, 'rateb_bi_')
            && str_contains($support, 'softLinkModules')
            && !preg_match('/\bFROM\s+rateb_crm_|\bUPDATE\s+rateb_crm_/i', $domain)
            && !preg_match('/\bFROM\s+rateb_payroll_|\bUPDATE\s+rateb_payroll_/i', $domain)
            && !preg_match('/\bFROM\s+rateb_dms_|\bUPDATE\s+rateb_dms_/i', $domain)
            && !str_contains($workflow, 'rateb_crm_')
            && !str_contains($domain, 'DocumentService')
            && in_array('crm', \Rateb\App\Services\BusinessIntelligenceSupport::softLinkModules(), true)
            && in_array('documents', \Rateb\App\Services\BusinessIntelligenceSupport::softLinkModules(), true);
        $this->record('BI soft-links modules only — no mutation of ERP tables', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $st = \Rateb\App\Services\BusinessIntelligenceWorkflowService::statuses('dashboard');
        $map = \Rateb\App\Services\BusinessIntelligenceWorkflowService::allowedTransitions('dashboard');
        $ok = in_array('draft', $st, true)
            && in_array('published', $st, true)
            && in_array('archived', $st, true)
            && in_array('published', $map['draft'] ?? [], true)
            && in_array('archived', $map['published'] ?? [], true)
            && ($map['archived'] ?? null) === []
            && \Rateb\App\Services\BusinessIntelligenceWorkflowService::statuses('report') === $st
            && \Rateb\App\Services\BusinessIntelligenceWorkflowService::statuses('kpi') === $st;
        $this->record('BI workflow maps', $ok, implode(',', $st));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\BusinessIntelligenceSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\BiDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiDashboardsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiKpisController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiReportsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiWidgetsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiDatasetsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiAlertsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiSchedulesController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiExportsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiTrendsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiForecastsController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiScopesController::class)
            && class_exists(\Rateb\App\Controllers\Company\BiAnalyticsController::class)
            && is_file(RATEB_ROOT . '/views/company/bi/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/bi/dashboards/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/dashboards/show.php')
            && is_file(RATEB_ROOT . '/views/company/bi/kpis/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/kpis/show.php')
            && is_file(RATEB_ROOT . '/views/company/bi/reports/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/reports/show.php')
            && is_file(RATEB_ROOT . '/views/company/bi/widgets/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/datasets/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/alerts/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/schedules/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/exports/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/trends/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/forecasts/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/scopes/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/analytics/index.php')
            && is_file(RATEB_ROOT . '/views/company/bi/timeline/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, 'bi-platform')
            && str_contains($routes, 'bi/dashboard')
            && str_contains($routes, 'bi/dashboards')
            && str_contains($routes, 'bi/kpis')
            && str_contains($routes, 'bi/reports')
            && str_contains($routes, 'bi/widgets')
            && str_contains($routes, 'bi/datasets')
            && str_contains($routes, 'bi/alerts')
            && str_contains($routes, 'bi/schedules')
            && str_contains($routes, 'bi/exports')
            && str_contains($routes, 'bi/trends')
            && str_contains($routes, 'bi/forecasts')
            && str_contains($routes, 'bi/analytics')
            && str_contains($routes, 'bi/timeline')
            && str_contains($routes, "rateb_erp_mw('bi'")
            && str_contains($routes, 'Phase 27A')
            && str_contains($routes, "app('reports')");
        $this->record('routes registered (bi/* + legacy reports preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $implies = $perms['permission_implies']['bi.manage'] ?? [];
        $ok = in_array('bi', $perms['company_modules'] ?? [], true)
            && in_array('bi.view', $implies, true)
            && in_array('bi.create', $implies, true)
            && in_array('bi.publish', $implies, true)
            && in_array('bi.export', $implies, true)
            && in_array('bi.admin', $implies, true)
            && isset($entities['bi'], $entities['bi-dashboards'], $entities['bi-reports'], $entities['bi-kpis'])
            && isset($labels['bi.create'], $labels['bi.admin'], $labels['bi.manage']);
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $ok = str_contains($nav, 'bi/dashboards')
            && str_contains($nav, 'bi/kpis')
            && str_contains($nav, 'bi.view')
            && isset($en['bi_platform'], $en['bi_reports'], $ar['bi_platform'], $ar['bi_kpis']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_27A_BUSINESS_INTELLIGENCE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '193_business_intelligence_platform.sql')
            && str_contains($doc, 'BusinessIntelligenceWorkflowService')
            && str_contains($doc, 'rateb_bi_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_27A_BUSINESS_INTELLIGENCE.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '27B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
