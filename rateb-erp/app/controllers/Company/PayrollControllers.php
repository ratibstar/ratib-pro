<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AdvanceService;
use Rateb\App\Services\LoanService;
use Rateb\App\Services\OvertimeService;
use Rateb\App\Services\PayrollBatchService;
use Rateb\App\Services\PayrollCalculationService;
use Rateb\App\Services\PayrollComponentService;
use Rateb\App\Services\PayrollCycleService;
use Rateb\App\Services\PayrollEnterpriseService;
use Rateb\App\Services\PayrollPayslipService;
use Rateb\App\Services\PayrollStructureService;
use Rateb\App\Services\PayrollSupport;
use Rateb\App\Services\PayrollTimelineService;
use Rateb\App\Services\PayrollWorkflowService;

/**
 * Phase 24A — Enterprise Payroll Platform ONLINE controllers (thin).
 * Additive payroll under /payroll/* — does not replace legacy hr/payroll or accounting GL.
 */
final class PayrollPlatformController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/dashboard')));
    }
}

final class PayrollDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/payroll/dashboard', [
            'title' => __('payroll_platform'),
            'board' => (new PayrollEnterpriseService())->boardCounts(),
            'timeline' => (new PayrollTimelineService())->listRecent(10),
            'canManage' => rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }
}

final class PayrollCyclesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new PayrollCycleService())->list($limit, ($page - 1) * $limit);
        $this->view('company/payroll/cycles/index', [
            'title' => __('payroll_cycles'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/cycles')));
        }
        try {
            (new PayrollCycleService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/cycles')));
    }
}

final class PayrollBatchesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new PayrollBatchService())->list($limit, ($page - 1) * $limit, $status !== '' ? $status : null);
        $this->view('company/payroll/batches/index', [
            'title' => __('payroll_batches'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'statuses' => PayrollWorkflowService::statuses(PayrollWorkflowService::ENTITY_BATCH),
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
            'canCalculate' => rateb_can('payroll.calculate') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/batches')));
        }
        try {
            $created = (new PayrollBatchService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url(rateb_app_route('payroll/batches') . '/' . $created['id']));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('payroll/batches')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = (new PayrollBatchService())->get($id);
        if ($item === null) {
            SessionManager::flash('error', __('record_not_found'));
            $this->redirect(rateb_url(rateb_app_route('payroll/batches')));
        }
        $this->view('company/payroll/batches/show', [
            'title' => (string) ($item['title'] ?? __('payroll_batches')),
            'item' => $item,
            'timeline' => (new PayrollTimelineService())->listForEntity('batch', $id),
            'transitions' => PayrollWorkflowService::allowedTransitions(PayrollWorkflowService::ENTITY_BATCH)
                [$item['workflow_status'] ?? 'draft'] ?? [],
            'canTransition' => rateb_can('payroll.review') || rateb_can('payroll.approve')
                || rateb_can('payroll.post') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
            'canCalculate' => rateb_can('payroll.calculate') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function calculate(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/batches')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new PayrollCalculationService())->calculateBatch($id);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/batches') . '/' . $id));
    }

    public function transition(array $params): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/batches')));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new PayrollWorkflowService())->transition(
                PayrollWorkflowService::ENTITY_BATCH,
                $id,
                (string) ($_POST['to_status'] ?? ''),
                trim((string) ($_POST['reason'] ?? '')) ?: null,
                PayrollSupport::intOrNull($_POST['expected_version'] ?? null)
            );
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/batches') . '/' . $id));
    }
}

final class PayrollPayslipsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $batchId = PayrollSupport::intOrNull($_GET['batch_id'] ?? null);
        $result = (new PayrollPayslipService())->list($limit, ($page - 1) * $limit, $batchId);
        $this->view('company/payroll/payslips/index', [
            'title' => __('payroll_payslips'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'batch_id' => $batchId,
        ], 'main');
    }
}

final class PayrollLoansController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new LoanService())->list($limit, ($page - 1) * $limit);
        $this->view('company/payroll/loans/index', [
            'title' => __('payroll_loans'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/loans')));
        }
        try {
            (new LoanService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/loans')));
    }
}

final class PayrollAdvancesController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new AdvanceService())->list($limit, ($page - 1) * $limit);
        $this->view('company/payroll/advances/index', [
            'title' => __('payroll_advances'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/loans')));
        }
        try {
            (new AdvanceService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/advances')));
    }
}

final class PayrollOvertimeController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new OvertimeService())->list($limit, ($page - 1) * $limit);
        $this->view('company/payroll/overtime/index', [
            'title' => __('payroll_overtime'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/overtime')));
        }
        try {
            (new OvertimeService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/overtime')));
    }
}

final class PayrollSalaryStructuresController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $search = trim((string) ($_GET['q'] ?? ''));
        $result = (new PayrollStructureService())->list($limit, ($page - 1) * $limit, $search);
        $this->view('company/payroll/salary-structures/index', [
            'title' => __('payroll_salary_structures'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'q' => $search,
            'canCreate' => rateb_can('payroll.create') || rateb_can('payroll.manage') || rateb_can('payroll.admin'),
        ], 'main');
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('payroll/salary-structures')));
        }
        try {
            (new PayrollStructureService())->create($_POST);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('payroll/salary-structures')));
    }
}

final class PayrollReportsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->view('company/payroll/reports/index', [
            'title' => __('payroll_reports'),
            'board' => (new PayrollEnterpriseService())->boardCounts(),
        ], 'main');
    }
}

final class PayrollTimelinePageController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = function_exists('rateb_list_per_page') ? rateb_list_per_page() : 25;
        $result = (new PayrollTimelineService())->listRecent($limit, ($page - 1) * $limit);
        $this->view('company/payroll/timeline/index', [
            'title' => __('payroll_timeline'),
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], 'main');
    }
}
