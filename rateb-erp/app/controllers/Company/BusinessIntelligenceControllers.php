<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\BiAlertService;
use Rateb\App\Services\BiAnalyticsScopeService;
use Rateb\App\Services\BiDashboardService;
use Rateb\App\Services\BiDatasetService;
use Rateb\App\Services\BiEnterpriseService;
use Rateb\App\Services\BiExportService;
use Rateb\App\Services\BiForecastService;
use Rateb\App\Services\BiKpiService;
use Rateb\App\Services\BiReportService;
use Rateb\App\Services\BiScheduleService;
use Rateb\App\Services\BiTimelineService;
use Rateb\App\Services\BiTrendService;
use Rateb\App\Services\BiWidgetService;
use Rateb\App\Services\BusinessIntelligenceSupport;
use Rateb\App\Services\BusinessIntelligenceWorkflowService;

/**
 * Phase 27A — Enterprise BI & Analytics Platform ONLINE controllers (thin).
 * Additive under /bi/* — soft-links modules only; Offline deferred to 27B.
 */
final class BiPlatformController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->redirect(rateb_url(rateb_app_route('bi/dashboard')));
    }
}

final class BiDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/bi/dashboard', [
            'title' => __('bi_platform'),
            'board' => (new BiEnterpriseService())->boardCounts(),
            'timeline' => (new BiTimelineService())->listRecent(10),
            'canManage' => rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }
}

final class BiDashboardsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiDashboardService())->list($limit, ($page - 1) * $limit, trim((string) ($_GET['q'] ?? '')));
        $this->view('company/bi/dashboards/index', [
            'title' => __('bi_dashboards'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/dashboards')));
        }
        try {
            (new BiDashboardService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/dashboards')));
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new BiDashboardService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('bi/dashboards')));
        }
        $this->view('company/bi/dashboards/show', [
            'title' => (string) ($item['name'] ?? __('bi_dashboards')),
            'item' => $item,
            'widgets' => (new BiWidgetService())->list(50, 0, $id)['items'],
            'timeline' => (new BiTimelineService())->listForEntity('dashboard', $id),
            'transitions' => BusinessIntelligenceWorkflowService::allowedTransitions(BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('bi.publish') || rateb_can('bi.update')
                || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/dashboards')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new BusinessIntelligenceWorkflowService())->transition(
                BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                BusinessIntelligenceSupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/dashboards') . '/' . $id));
    }
}

final class BiKpisController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiKpiService())->list($limit, ($page - 1) * $limit, trim((string) ($_GET['q'] ?? '')));
        $this->view('company/bi/kpis/index', [
            'title' => __('bi_kpis'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
            'modules' => BusinessIntelligenceSupport::softLinkModules(),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/kpis')));
        }
        try {
            (new BiKpiService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/kpis')));
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new BiKpiService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('bi/kpis')));
        }
        $this->view('company/bi/kpis/show', [
            'title' => (string) ($item['name'] ?? __('bi_kpis')),
            'item' => $item,
            'timeline' => (new BiTimelineService())->listForEntity('kpi', $id),
            'transitions' => BusinessIntelligenceWorkflowService::allowedTransitions(BusinessIntelligenceWorkflowService::ENTITY_KPI)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('bi.publish') || rateb_can('bi.update')
                || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/kpis')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new BusinessIntelligenceWorkflowService())->transition(
                BusinessIntelligenceWorkflowService::ENTITY_KPI,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                BusinessIntelligenceSupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/kpis') . '/' . $id));
    }
}

final class BiReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiReportService())->list(
            $limit,
            ($page - 1) * $limit,
            trim((string) ($_GET['q'] ?? '')),
            trim((string) ($_GET['report_type'] ?? '')) ?: null
        );
        $this->view('company/bi/reports/index', [
            'title' => __('bi_reports'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
            'modules' => BusinessIntelligenceSupport::softLinkModules(),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/reports')));
        }
        try {
            (new BiReportService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/reports')));
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new BiReportService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('bi/reports')));
        }
        $this->view('company/bi/reports/show', [
            'title' => (string) ($item['name'] ?? __('bi_reports')),
            'item' => $item,
            'timeline' => (new BiTimelineService())->listForEntity('report', $id),
            'transitions' => BusinessIntelligenceWorkflowService::allowedTransitions(BusinessIntelligenceWorkflowService::ENTITY_REPORT)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('bi.publish') || rateb_can('bi.update')
                || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/reports')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new BusinessIntelligenceWorkflowService())->transition(
                BusinessIntelligenceWorkflowService::ENTITY_REPORT,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                BusinessIntelligenceSupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/reports') . '/' . $id));
    }
}

final class BiWidgetsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $dashId = BusinessIntelligenceSupport::intOrNull($_GET['dashboard_id'] ?? null);
        $result = (new BiWidgetService())->list($limit, ($page - 1) * $limit, $dashId);
        $this->view('company/bi/widgets/index', [
            'title' => __('bi_widgets'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/widgets')));
        }
        try {
            (new BiWidgetService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/widgets')));
    }
}

final class BiDatasetsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiDatasetService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/datasets/index', [
            'title' => __('bi_datasets'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
            'modules' => BusinessIntelligenceSupport::softLinkModules(),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/datasets')));
        }
        try {
            (new BiDatasetService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/datasets')));
    }
}

final class BiAlertsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiAlertService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/alerts/index', [
            'title' => __('bi_alerts'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/alerts')));
        }
        try {
            (new BiAlertService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/alerts')));
    }
}

final class BiSchedulesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiScheduleService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/schedules/index', [
            'title' => __('bi_schedules'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/schedules')));
        }
        try {
            (new BiScheduleService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/schedules')));
    }
}

final class BiExportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiExportService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/exports/index', [
            'title' => __('bi_exports'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canExport' => rateb_can('bi.export') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/exports')));
        }
        try {
            (new BiExportService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/exports')));
    }
}

final class BiTrendsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiTrendService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/trends/index', [
            'title' => __('bi_trends'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/trends')));
        }
        try {
            (new BiTrendService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/trends')));
    }
}

final class BiForecastsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiForecastService())->list($limit, ($page - 1) * $limit);
        $this->view('company/bi/forecasts/index', [
            'title' => __('bi_forecasts'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/forecasts')));
        }
        try {
            (new BiForecastService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/forecasts')));
    }
}

final class BiScopesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiAnalyticsScopeService())->list(
            $limit,
            ($page - 1) * $limit,
            trim((string) ($_GET['scope_type'] ?? '')) ?: null
        );
        $this->view('company/bi/scopes/index', [
            'title' => __('bi_scopes'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('bi.create') || rateb_can('bi.manage') || rateb_can('bi.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('bi/scopes')));
        }
        try {
            (new BiAnalyticsScopeService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('bi/scopes')));
    }
}

final class BiAnalyticsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/bi/analytics/index', [
            'title' => __('bi_analytics'),
            'board' => (new BiEnterpriseService())->boardCounts(),
            'modules' => BusinessIntelligenceSupport::softLinkModules(),
        ], 'main');
    }
}

final class BiTimelinePageController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new BiTimelineService())->listPaged($limit, ($page - 1) * $limit);
        $this->view('company/bi/timeline/index', [
            'title' => __('bi_timeline'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'main');
    }
}
