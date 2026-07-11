<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\CompetencyService;
use Rateb\App\Services\DepartmentService;
use Rateb\App\Services\EmployeeProfileService;
use Rateb\App\Services\EmployeeTimelineService;
use Rateb\App\Services\GoalService;
use Rateb\App\Services\HumanResourcesEnterpriseService;
use Rateb\App\Services\HumanResourcesWorkflowService;
use Rateb\App\Services\OrganizationService;
use Rateb\App\Services\PerformanceReviewService;
use Rateb\App\Services\PositionService;
use Rateb\App\Services\PromotionService;
use Rateb\App\Services\TrainingService;
use Rateb\App\Services\TransferService;

/**
 * Phase 23A — Enterprise Human Resources (HRMS) ONLINE controllers (thin).
 * Additive HR under /hrm/* — does not replace legacy hr/*, payroll, attendance, or leave.
 */
final class HrmDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/dashboard', [
            'title' => __('hr_platform'),
            'board' => (new HumanResourcesEnterpriseService())->boardCounts(),
            'timeline' => (new EmployeeTimelineService())->listRecent(10),
            'canManage' => rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }
}

final class HrmEmployeesController extends Controller
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
        $result = (new EmployeeProfileService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/hrm/employees/index', [
            'title' => __('hrm_employees'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => HumanResourcesWorkflowService::statuses(HumanResourcesWorkflowService::ENTITY_EMPLOYEE),
            'canCreate' => rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/employees/form', [
            'title' => __('hrm_employee_create'),
            'item' => null,
            'departments' => (new DepartmentService())->list(100, 0)['items'],
            'positions' => (new PositionService())->list(100, 0)['items'],
            'action' => rateb_url(rateb_app_route('hrm/employees')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/employees')));
        }
        try {
            $created = (new EmployeeProfileService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('hrm/employees') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('hrm/employees/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new EmployeeProfileService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('hrm/employees')));
        }
        $name = trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? ''));
        $this->view('company/hrm/employees/show', [
            'title' => $name !== '' ? $name : __('hrm_employees'),
            'item' => $item,
            'timeline' => (new EmployeeTimelineService())->listForEntity(
                HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
                $id
            ),
            'transitions' => HumanResourcesWorkflowService::allowedTransitions(
                HumanResourcesWorkflowService::ENTITY_EMPLOYEE
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('hr.update') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/employees')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HumanResourcesWorkflowService())->transition(
                HumanResourcesWorkflowService::ENTITY_EMPLOYEE,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/employees') . '/' . $id));
    }
}

final class HrmDepartmentsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new DepartmentService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/hrm/departments/index', [
            'title' => __('hrm_departments'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'canCreate' => rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/departments')));
        }
        try {
            (new DepartmentService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/departments')));
    }
}

final class HrmPositionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new PositionService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/hrm/positions/index', [
            'title' => __('hrm_positions'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'departments' => (new DepartmentService())->list(100, 0)['items'],
            'canCreate' => rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/positions')));
        }
        try {
            (new PositionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/positions')));
    }
}

final class HrmOrganizationController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $org = new OrganizationService();
        $this->view('company/hrm/organization/index', [
            'title' => __('hrm_organization'),
            'units' => $org->listOrgUnits(100, 0)['items'],
            'locations' => $org->listLocations(100, 0)['items'],
            'canCreate' => rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function storeUnit(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/organization')));
        }
        try {
            (new OrganizationService())->createOrgUnit($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/organization')));
    }

    public function storeLocation(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/organization')));
        }
        try {
            (new OrganizationService())->createLocation($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/organization')));
    }
}

final class HrmTrainingController extends Controller
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
        $result = (new TrainingService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/hrm/training/index', [
            'title' => __('hrm_training'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => HumanResourcesWorkflowService::statuses(HumanResourcesWorkflowService::ENTITY_TRAINING),
            'canCreate' => rateb_can('hr.training') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/training/form', [
            'title' => __('hrm_training_create'),
            'item' => null,
            'action' => rateb_url(rateb_app_route('hrm/training')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/training')));
        }
        try {
            $created = (new TrainingService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('hrm/training') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('hrm/training/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new TrainingService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('hrm/training')));
        }
        $this->view('company/hrm/training/show', [
            'title' => $item['title'] ?? __('hrm_training'),
            'item' => $item,
            'enrollments' => (new TrainingService())->listEnrollments($id),
            'timeline' => (new EmployeeTimelineService())->listForEntity(
                HumanResourcesWorkflowService::ENTITY_TRAINING,
                $id
            ),
            'transitions' => HumanResourcesWorkflowService::allowedTransitions(
                HumanResourcesWorkflowService::ENTITY_TRAINING
            )[$item['workflow_status'] ?? 'planned'] ?? [],
            'canUpdate' => rateb_can('hr.training') || rateb_can('hr.update') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/training')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HumanResourcesWorkflowService())->transition(
                HumanResourcesWorkflowService::ENTITY_TRAINING,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/training') . '/' . $id));
    }
}

final class HrmPerformanceController extends Controller
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
        $result = (new PerformanceReviewService())->list($limit, ($page - 1) * $limit, $search, $status !== '' ? $status : null);
        $this->view('company/hrm/performance/index', [
            'title' => __('hrm_performance'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'status' => $status,
            'statuses' => HumanResourcesWorkflowService::statuses(HumanResourcesWorkflowService::ENTITY_PERFORMANCE),
            'canCreate' => rateb_can('hr.performance') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/performance/form', [
            'title' => __('hrm_performance_create'),
            'item' => null,
            'employees' => (new EmployeeProfileService())->list(100, 0)['items'],
            'action' => rateb_url(rateb_app_route('hrm/performance')),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/performance')));
        }
        try {
            $created = (new PerformanceReviewService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('hrm/performance') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('hrm/performance/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new PerformanceReviewService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('hrm/performance')));
        }
        $this->view('company/hrm/performance/show', [
            'title' => $item['code'] ?? __('hrm_performance'),
            'item' => $item,
            'timeline' => (new EmployeeTimelineService())->listForEntity(
                HumanResourcesWorkflowService::ENTITY_PERFORMANCE,
                $id
            ),
            'transitions' => HumanResourcesWorkflowService::allowedTransitions(
                HumanResourcesWorkflowService::ENTITY_PERFORMANCE
            )[$item['workflow_status'] ?? 'draft'] ?? [],
            'canUpdate' => rateb_can('hr.performance') || rateb_can('hr.update') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/performance')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HumanResourcesWorkflowService())->transition(
                HumanResourcesWorkflowService::ENTITY_PERFORMANCE,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                isset($_POST['reason']) ? (string) $_POST['reason'] : null,
                isset($_POST['expected_version']) ? (int) $_POST['expected_version'] : null
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/performance') . '/' . $id));
    }
}

final class HrmPromotionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new PromotionService())->list($limit, ($page - 1) * $limit, null, $search);
        $this->view('company/hrm/promotions/index', [
            'title' => __('hrm_promotions'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'employees' => (new EmployeeProfileService())->list(100, 0)['items'],
            'positions' => (new PositionService())->list(100, 0)['items'],
            'canCreate' => rateb_can('hr.promotions') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/promotions')));
        }
        try {
            (new PromotionService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/promotions')));
    }
}

final class HrmTransfersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new TransferService())->list($limit, ($page - 1) * $limit, null, $search);
        $this->view('company/hrm/transfers/index', [
            'title' => __('hrm_transfers'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'employees' => (new EmployeeProfileService())->list(100, 0)['items'],
            'departments' => (new DepartmentService())->list(100, 0)['items'],
            'canCreate' => rateb_can('hr.transfers') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/transfers')));
        }
        try {
            (new TransferService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/transfers')));
    }
}

final class HrmGoalsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new GoalService())->list($limit, ($page - 1) * $limit, null, $search);
        $this->view('company/hrm/goals/index', [
            'title' => __('hrm_goals'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'employees' => (new EmployeeProfileService())->list(100, 0)['items'],
            'canCreate' => rateb_can('hr.performance') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/goals')));
        }
        try {
            (new GoalService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/goals')));
    }
}

final class HrmCompetenciesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new CompetencyService())->list($limit, ($page - 1) * $limit, null, $search);
        $this->view('company/hrm/competencies/index', [
            'title' => __('hrm_competencies'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'canCreate' => rateb_can('hr.performance') || rateb_can('hr.create') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hrm/competencies')));
        }
        try {
            (new CompetencyService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('hrm/competencies')));
    }
}

final class HrmReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/reports/index', [
            'title' => __('hrm_reports'),
            'board' => (new HumanResourcesEnterpriseService())->boardCounts(),
            'timeline' => (new EmployeeTimelineService())->listRecent(20),
            'canView' => rateb_can('hr.view') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }
}

final class HrmTimelineController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/hrm/timeline/index', [
            'title' => __('hrm_timeline'),
            'timeline' => (new EmployeeTimelineService())->listRecent(50),
            'canView' => rateb_can('hr.view') || rateb_can('hr.manage') || rateb_can('hr.admin'),
        ], 'main');
    }
}
