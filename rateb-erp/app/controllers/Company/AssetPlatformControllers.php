<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AssetActivityService;
use Rateb\App\Services\AssetAssignmentService;
use Rateb\App\Services\AssetCommentService;
use Rateb\App\Services\AssetService;
use Rateb\App\Services\AssetTimelineService;
use Rateb\App\Services\AssetTransferService;
use Rateb\App\Services\AssetWorkflowService;
use Rateb\App\Services\InspectionService;
use Rateb\App\Services\MaintenancePlanService;
use Rateb\App\Services\MaintenanceRequestService;
use Rateb\App\Services\MeterReadingService;
use Rateb\App\Services\WorkOrderService;

/**
 * Phase 19A — Enterprise Assets & Maintenance ONLINE controllers (thin).
 * Routes under /eam/* — does not replace legacy /assets register.
 */
final class EamDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $assets = (new AssetService())->list(8, 0, '');
        $this->view('company/eam/dashboard', [
            'title' => __('eam_platform'),
            'recent' => $assets['items'],
            'total' => $assets['total'],
            'board' => (new AssetService())->boardCounts(),
            'requests' => (new MaintenanceRequestService())->boardCounts(),
            'timeline' => (new AssetTimelineService())->listRecent(10),
            'statuses' => AssetWorkflowService::assetStatuses(),
        ], 'main');
    }
}

final class EamAssetsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new AssetService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/eam/assets/index', [
            'title' => __('eam_assets'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => AssetWorkflowService::assetStatuses(),
            'canCreate' => rateb_can('assets.create') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/assets/form', [
            'title' => __('eam_asset_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('eam/assets')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        try {
            $created = (new AssetService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eam/assets/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new AssetService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $this->view('company/eam/assets/show', [
            'title' => $item['name'] ?? __('eam_assets'),
            'item' => $item,
            'assignments' => (new AssetAssignmentService())->list($id, 20),
            'transfers' => (new AssetTransferService())->list($id, 20),
            'meters' => (new MeterReadingService())->listForAsset($id, 20),
            'comments' => (new AssetCommentService())->listFor('asset', $id),
            'timeline' => (new AssetTimelineService())->listForAsset($id),
            'statuses' => AssetWorkflowService::assetStatuses(),
            'transitions' => AssetWorkflowService::allowedAssetTransitions()[$item['workflow_status'] ?? 'draft'] ?? [],
            'canWorkflow' => rateb_can('assets.update') || rateb_can('assets.manage') || rateb_can('assets.admin'),
            'canAssign' => rateb_can('assets.assign') || rateb_can('assets.manage'),
            'canTransfer' => rateb_can('assets.transfer') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new AssetService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $this->view('company/eam/assets/form', [
            'title' => __('eam_asset_edit'),
            'item' => $item,
            'action' => rateb_url(rateb_app_route('eam/assets') . '/' . $id),
        ], 'main');
    }

    public function update(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetService())->update($id, $_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id . '/edit'));
        }
    }

    public function destroy(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetService())->softDelete($id);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/assets')));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetWorkflowService())->transitionAsset(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id));
    }

    public function assign(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetAssignmentService())->assign(array_merge($_POST, ['asset_id' => $id]));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id));
    }

    public function transfer(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $created = (new AssetTransferService())->create(array_merge($_POST, ['asset_id' => $id]));
            if (!empty($_POST['complete_now'])) {
                (new AssetTransferService())->complete((int) $created['id']);
            }
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id));
    }

    public function storeComment(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/assets')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetCommentService())->create(array_merge($_POST, [
                'related_type' => 'asset',
                'related_id' => $id,
                'asset_id' => $id,
            ]));
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/assets') . '/' . $id));
    }
}

final class EamMaintenanceController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/maintenance/index', [
            'title' => __('eam_maintenance'),
            'plans' => (new MaintenancePlanService())->list(null, 30),
            'requests' => (new MaintenanceRequestService())->list(20, 0)['items'],
            'canCreate' => rateb_can('assets.maintenance') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function storePlan(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/maintenance')));
        }
        try {
            (new MaintenancePlanService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/maintenance')));
    }
}

final class EamRequestsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new MaintenanceRequestService())->list(50, 0, $status !== '' ? $status : null);
        $this->view('company/eam/requests/index', [
            'title' => __('eam_requests'),
            'items' => $result['items'],
            'total' => $result['total'],
            'status' => $status,
            'statuses' => AssetWorkflowService::requestStatuses(),
            'board' => (new MaintenanceRequestService())->boardCounts(),
            'canCreate' => rateb_can('assets.maintenance') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/requests')));
        }
        try {
            $created = (new MaintenanceRequestService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eam/requests') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eam/requests')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new MaintenanceRequestService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eam/requests')));
        }
        $this->view('company/eam/requests/show', [
            'title' => $item['title'] ?? __('eam_requests'),
            'item' => $item,
            'transitions' => AssetWorkflowService::allowedRequestTransitions()[$item['workflow_status'] ?? 'new'] ?? [],
            'canWorkflow' => rateb_can('assets.maintenance') || rateb_can('assets.manage') || rateb_can('assets.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/requests')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetWorkflowService())->transitionMaintenanceRequest(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/requests') . '/' . $id));
    }
}

final class EamWorkOrdersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new WorkOrderService())->list(50, 0, $status !== '' ? $status : null);
        $this->view('company/eam/work-orders/index', [
            'title' => __('eam_work_orders'),
            'items' => $result['items'],
            'total' => $result['total'],
            'status' => $status,
            'statuses' => AssetWorkflowService::requestStatuses(),
            'canCreate' => rateb_can('assets.maintenance') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/work-orders')));
        }
        try {
            $created = (new WorkOrderService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('eam/work-orders') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('eam/work-orders')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new WorkOrderService())->find($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('eam/work-orders')));
        }
        $this->view('company/eam/work-orders/show', [
            'title' => $item['title'] ?? __('eam_work_orders'),
            'item' => $item,
            'transitions' => AssetWorkflowService::allowedRequestTransitions()[$item['workflow_status'] ?? 'new'] ?? [],
            'canWorkflow' => rateb_can('assets.maintenance') || rateb_can('assets.manage') || rateb_can('assets.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/work-orders')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new AssetWorkflowService())->transitionWorkOrder(
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/work-orders') . '/' . $id));
    }
}

final class EamCalendarController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-t')));
        $this->view('company/eam/calendar', [
            'title' => __('eam_calendar'),
            'items' => (new WorkOrderService())->calendar($from, $to),
            'from' => $from,
            'to' => $to,
        ], 'main');
    }
}

final class EamAssignmentsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/assignments', [
            'title' => __('eam_assignments'),
            'items' => (new AssetAssignmentService())->list(null, 100),
            'canAssign' => rateb_can('assets.assign') || rateb_can('assets.manage'),
        ], 'main');
    }
}

final class EamTimelineController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/timeline', [
            'title' => __('eam_timeline'),
            'timeline' => (new AssetTimelineService())->listRecent(50),
        ], 'main');
    }

    public function storeActivity(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/timeline')));
        }
        try {
            (new AssetActivityService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/timeline')));
    }
}

final class EamInspectionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/inspections', [
            'title' => __('eam_inspections'),
            'items' => (new InspectionService())->list(null, 50),
            'canCreate' => rateb_can('assets.inspection') || rateb_can('assets.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('eam/inspections')));
        }
        try {
            (new InspectionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('eam/inspections')));
    }
}

final class EamReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/eam/reports', [
            'title' => __('eam_reports'),
            'assetBoard' => (new AssetService())->boardCounts(),
            'requestBoard' => (new MaintenanceRequestService())->boardCounts(),
            'assetTotal' => (new AssetService())->list(1, 0)['total'],
            'requestTotal' => (new MaintenanceRequestService())->list(1, 0)['total'],
            'workOrderTotal' => (new WorkOrderService())->list(1, 0)['total'],
        ], 'main');
    }
}
