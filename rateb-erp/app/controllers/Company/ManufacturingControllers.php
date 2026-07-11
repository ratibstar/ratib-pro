<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\BomService;
use Rateb\App\Services\BomVersionService;
use Rateb\App\Services\CapacityPlanService;
use Rateb\App\Services\FinishedGoodsReceiptService;
use Rateb\App\Services\ManufacturingCommentService;
use Rateb\App\Services\ManufacturingEnterpriseService;
use Rateb\App\Services\ManufacturingSupport;
use Rateb\App\Services\ManufacturingTimelineService;
use Rateb\App\Services\ManufacturingWorkflowService;
use Rateb\App\Services\MaterialConsumptionService;
use Rateb\App\Services\MaterialReservationService;
use Rateb\App\Services\MfgProductService;
use Rateb\App\Services\ProductionCalendarService;
use Rateb\App\Services\ProductionCostService;
use Rateb\App\Services\ProductionOrderService;
use Rateb\App\Services\QualityCheckService;
use Rateb\App\Services\RoutingOperationService;
use Rateb\App\Services\RoutingService;
use Rateb\App\Services\ScheduleService;
use Rateb\App\Services\ScrapRecordingService;
use Rateb\App\Services\WorkCenterService;
use Rateb\App\Services\MfgWorkOrderService;

/**
 * Phase 22A — Enterprise Manufacturing (MRP) ONLINE controllers (thin).
 * Additive MFG under /mfg/* — does not replace legacy inventory/production screens.
 */
final class MfgDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/dashboard', [
            'title' => __('manufacturing_platform'),
            'board' => (new ManufacturingEnterpriseService())->boardCounts(),
            'timeline' => (new ManufacturingTimelineService())->listRecent(10),
            'canManage' => rateb_can('manufacturing.manage') || rateb_can('manufacturing.admin'),
        ], 'main');
    }
}

final class MfgProductsController extends Controller
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
        $result = (new MfgProductService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/mfg/products/index', [
            'title' => __('mfg_products'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ManufacturingWorkflowService::statuses(ManufacturingWorkflowService::ENTITY_PRODUCT),
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/products/form', [
            'title' => __('mfg_product_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('mfg/products')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/products')));
        }
        try {
            $created = (new MfgProductService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('mfg/products') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('mfg/products/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new MfgProductService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('mfg/products')));
        }
        $this->view('company/mfg/products/show', [
            'title' => $item['name'] ?? __('mfg_products'),
            'item' => $item,
            'timeline' => (new ManufacturingTimelineService())->listForEntity('product', $id),
            'transitions' => ManufacturingWorkflowService::allowedTransitions(
                ManufacturingWorkflowService::ENTITY_PRODUCT
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('manufacturing.update') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/products')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ManufacturingWorkflowService())->transition(
                ManufacturingWorkflowService::ENTITY_PRODUCT,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/products') . '/' . $id));
    }
}

final class MfgBomsController extends Controller
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
        $result = (new BomService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/mfg/boms/index', [
            'title' => __('mfg_boms'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ManufacturingWorkflowService::statuses(ManufacturingWorkflowService::ENTITY_BOM),
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.bom') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/boms/form', [
            'title' => __('mfg_bom_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('mfg/boms')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/boms')));
        }
        try {
            $created = (new BomService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('mfg/boms') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('mfg/boms/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new BomService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('mfg/boms')));
        }
        $this->view('company/mfg/boms/show', [
            'title' => $item['name'] ?? __('mfg_boms'),
            'item' => $item,
            'versions' => (new BomVersionService())->listByBom($id),
            'timeline' => (new ManufacturingTimelineService())->listForEntity('bom', $id),
            'transitions' => ManufacturingWorkflowService::allowedTransitions(
                ManufacturingWorkflowService::ENTITY_BOM
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('manufacturing.update') || rateb_can('manufacturing.bom') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/boms')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ManufacturingWorkflowService())->transition(
                ManufacturingWorkflowService::ENTITY_BOM,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/boms') . '/' . $id));
    }
}

final class MfgProductionOrdersController extends Controller
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
        $result = (new ProductionOrderService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/mfg/production-orders/index', [
            'title' => __('mfg_production_orders'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ManufacturingWorkflowService::statuses(ManufacturingWorkflowService::ENTITY_PRODUCTION_ORDER),
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.shopfloor') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/production-orders/form', [
            'title' => __('mfg_production_order_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('mfg/production-orders')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/production-orders')));
        }
        try {
            $created = (new ProductionOrderService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('mfg/production-orders') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('mfg/production-orders/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new ProductionOrderService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('mfg/production-orders')));
        }
        $quality = (new QualityCheckService())->list(50, 0, $id);
        $this->view('company/mfg/production-orders/show', [
            'title' => $item['title'] ?? __('mfg_production_orders'),
            'item' => $item,
            'workOrders' => (new MfgWorkOrderService())->listByProductionOrder($id),
            'reservations' => (new MaterialReservationService())->listByProductionOrder($id),
            'consumptions' => (new MaterialConsumptionService())->listByProductionOrder($id),
            'receipts' => (new FinishedGoodsReceiptService())->listByProductionOrder($id),
            'scrap' => (new ScrapRecordingService())->listByProductionOrder($id),
            'quality' => $quality['items'],
            'costs' => (new ProductionCostService())->listByProductionOrder($id),
            'comments' => (new ManufacturingCommentService())->listForEntity('production_order', $id),
            'timeline' => (new ManufacturingTimelineService())->listForEntity('production_order', $id),
            'transitions' => ManufacturingWorkflowService::allowedTransitions(
                ManufacturingWorkflowService::ENTITY_PRODUCTION_ORDER
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('manufacturing.update') || rateb_can('manufacturing.shopfloor') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/production-orders')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ManufacturingWorkflowService())->transition(
                ManufacturingWorkflowService::ENTITY_PRODUCTION_ORDER,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/production-orders') . '/' . $id));
    }
}

final class MfgWorkOrdersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new MfgWorkOrderService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/mfg/work-orders/index', [
            'title' => __('mfg_work_orders'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => '',
            'status' => $status,
            'statuses' => ManufacturingWorkflowService::statuses(ManufacturingWorkflowService::ENTITY_WORK_ORDER),
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.shopfloor') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/work-orders/form', [
            'title' => __('mfg_work_orders'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('mfg/work-orders')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/work-orders')));
        }
        try {
            $created = (new MfgWorkOrderService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('mfg/work-orders') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('mfg/work-orders/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = ManufacturingSupport::findWorkOrder($id, ManufacturingSupport::requireCompanyId());
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('mfg/work-orders')));
        }
        $this->view('company/mfg/work-orders/show', [
            'title' => $item['title'] ?? __('mfg_work_orders'),
            'item' => $item,
            'timeline' => (new ManufacturingTimelineService())->listForEntity('work_order', $id),
            'transitions' => ManufacturingWorkflowService::allowedTransitions(
                ManufacturingWorkflowService::ENTITY_WORK_ORDER
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('manufacturing.update') || rateb_can('manufacturing.shopfloor') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/work-orders')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new ManufacturingWorkflowService())->transition(
                ManufacturingWorkflowService::ENTITY_WORK_ORDER,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/work-orders') . '/' . $id));
    }
}

final class MfgWorkCentersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new WorkCenterService())->list(100, 0);
        $this->view('company/mfg/work-centers/index', [
            'title' => __('mfg_work_centers'),
            'items' => $result['items'],
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/work-centers')));
        }
        try {
            (new WorkCenterService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/work-centers')));
    }
}

final class MfgRoutingsController extends Controller
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
        $result = (new RoutingService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/mfg/routings/index', [
            'title' => __('mfg_routings'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => ManufacturingWorkflowService::statuses(ManufacturingWorkflowService::ENTITY_ROUTING),
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/routings')));
        }
        try {
            $created = (new RoutingService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('mfg/routings') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('mfg/routings')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new RoutingService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('mfg/routings')));
        }
        $this->view('company/mfg/routings/show', [
            'title' => $item['name'] ?? __('mfg_routings'),
            'item' => $item,
            'operations' => (new RoutingOperationService())->listByRouting($id),
            'timeline' => (new ManufacturingTimelineService())->listForEntity('routing', $id),
        ], 'main');
    }
}

final class MfgCapacityController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new CapacityPlanService())->list(100, 0);
        $this->view('company/mfg/capacity/index', [
            'title' => __('mfg_capacity'),
            'items' => $result['items'],
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.planning') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/capacity')));
        }
        try {
            (new CapacityPlanService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/capacity')));
    }
}

final class MfgCalendarController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new ProductionCalendarService())->list(100, 0);
        $this->view('company/mfg/calendar/index', [
            'title' => __('mfg_calendar'),
            'items' => $result['items'],
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.planning') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/calendar')));
        }
        try {
            (new ProductionCalendarService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/calendar')));
    }
}

final class MfgSchedulesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $result = (new ScheduleService())->list(100, 0);
        $this->view('company/mfg/schedules/index', [
            'title' => __('mfg_schedules'),
            'items' => $result['items'],
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.planning') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/schedules')));
        }
        try {
            (new ScheduleService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/schedules')));
    }
}

final class MfgQualityController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new QualityCheckService())->list($limit, ($page - 1) * $limit);
        $this->view('company/mfg/quality/index', [
            'title' => __('mfg_quality'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('manufacturing.create') || rateb_can('manufacturing.quality') || rateb_can('manufacturing.manage'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('mfg/quality')));
        }
        try {
            (new QualityCheckService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('mfg/quality')));
    }
}

final class MfgReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/mfg/reports/index', [
            'title' => __('mfg_reports'),
            'board' => (new ManufacturingEnterpriseService())->boardCounts(),
            'timeline' => (new ManufacturingTimelineService())->listRecent(20),
            'canView' => rateb_can('manufacturing.view') || rateb_can('manufacturing.manage'),
        ], 'main');
    }
}
