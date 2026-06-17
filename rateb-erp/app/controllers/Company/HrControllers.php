<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DocumentCodeService;
use Rateb\App\Services\HrService;

final class HrDashboardController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $stats = (new HrService())->dashboardStats($companyId);
        $this->view('company/hr/dashboard', [
            'title' => __('human_resources'),
            'stats' => $stats,
            'companyId' => $companyId,
        ], 'main');
    }
}

final class HrEmployeesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\Employee();
        $this->viewPrefix = 'company/hr/employees';
        $this->routePrefix = rateb_app_route('hr/employees');
        $this->entityName = 'hr_employees';
        $this->permissionResource = 'hr-employees';
        $this->tenantForeignKeys = ['department_id'];
        $this->indexFields = [
            ['name' => 'employee_code', 'label' => 'employee_code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'department_id', 'label' => 'department', 'type' => 'fk', 'lookup' => 'hr_departments'],
            ['name' => 'job_title', 'label' => 'job_title'],
            ['name' => 'salary_base', 'label' => 'salary_base'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'phone', 'type' => 'text'],
            ['name' => 'national_id', 'label' => 'national_id', 'type' => 'text'],
            ['name' => 'department_id', 'label' => 'department', 'type' => 'fk', 'lookup' => 'hr_departments'],
            ['name' => 'job_title', 'label' => 'job_title', 'type' => 'text'],
            ['name' => 'hire_date', 'label' => 'hire_date', 'type' => 'date'],
            ['name' => 'salary_base', 'label' => 'salary_base', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num']],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'employee_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, DocumentCodeService::PREFIX_EMPLOYEE, 'employee_code');
        return $data;
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $profile = (new HrService())->employeeProfile($id);
        if ($profile === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/show', array_merge($profile, [
            'title' => (string) ($profile['employee']['name'] ?? __('hr_employees')),
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'canManage' => function_exists('rateb_can_manage_entity') ? rateb_can_manage_entity('hr-employees') : true,
        ]), $this->layout());
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $rows = (new \Rateb\App\Models\Employee())->all(5000, 0);
        $lookups = (new \Rateb\App\Services\FormLookupService())->get('hr_departments');
        $deptMap = [];
        foreach ($lookups as $opt) {
            $deptMap[(string) $opt['value']] = (string) $opt['label'];
        }
        $exportRows = [];
        foreach ($rows as $row) {
            $exportRows[] = [
                'employee_code' => $row['employee_code'] ?? '',
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'department' => $deptMap[(string) ($row['department_id'] ?? '')] ?? '',
                'job_title' => $row['job_title'] ?? '',
                'hire_date' => $row['hire_date'] ?? '',
                'salary_base' => $row['salary_base'] ?? '',
                'status' => __((string) ($row['status'] ?? 'active')),
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('employees', [
            ['name' => 'employee_code', 'label' => __('employee_code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'email', 'label' => __('email')],
            ['name' => 'phone', 'label' => __('phone')],
            ['name' => 'department', 'label' => __('department')],
            ['name' => 'job_title', 'label' => __('job_title')],
            ['name' => 'hire_date', 'label' => __('hire_date')],
            ['name' => 'salary_base', 'label' => __('salary_base')],
            ['name' => 'status', 'label' => __('status')],
        ], $exportRows, __('hr_employees'), 'hr-employees');
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrDepartmentsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrDepartment();
        $this->viewPrefix = 'company/hr/departments';
        $this->routePrefix = rateb_app_route('hr/departments');
        $this->entityName = 'hr_departments';
        $this->permissionResource = 'hr-employees';
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'code', 'label' => 'code', 'type' => 'text'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrAttendanceController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\AttendanceRecord();
        $this->viewPrefix = 'company/hr/attendance';
        $this->routePrefix = rateb_app_route('hr/attendance');
        $this->entityName = 'hr_attendance';
        $this->permissionResource = 'hr-attendance';
        $this->tenantForeignKeys = ['employee_id'];
        $this->indexFields = [
            ['name' => 'attendance_date', 'label' => 'attendance_date'],
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'check_in', 'label' => 'check_in'],
            ['name' => 'check_out', 'label' => 'check_out'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'attendance_date', 'label' => 'attendance_date', 'type' => 'date', 'required' => true],
            ['name' => 'check_in', 'label' => 'check_in', 'type' => 'text', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num', 'placeholder' => '09:00']],
            ['name' => 'check_out', 'label' => 'check_out', 'type' => 'text', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num', 'placeholder' => '17:00']],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'attendance_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrLeavesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\LeaveRequest();
        $this->viewPrefix = 'company/hr/leaves';
        $this->routePrefix = rateb_app_route('hr/leaves');
        $this->entityName = 'hr_leaves';
        $this->permissionResource = 'hr-leaves';
        $this->tenantForeignKeys = ['employee_id', 'leave_type_id'];
        $this->indexFields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'leave_type_id', 'label' => 'leave_type', 'type' => 'fk', 'lookup' => 'leave_types'],
            ['name' => 'start_date', 'label' => 'start_date'],
            ['name' => 'end_date', 'label' => 'end_date'],
            ['name' => 'days', 'label' => 'days'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'leave_type_id', 'label' => 'leave_type', 'type' => 'fk', 'lookup' => 'leave_types', 'required' => true],
            ['name' => 'start_date', 'label' => 'start_date', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'end_date', 'type' => 'date', 'required' => true],
            ['name' => 'days', 'label' => 'days', 'type' => 'number', 'step' => '0.5', 'min' => '0.5'],
            ['name' => 'reason', 'label' => 'reason', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'leave_request_statuses', 'translate_options' => true],
        ];
    }

    public function index(): void
    {
        HrService::bootstrapTenant();
        $page = max(1, (int) $this->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $year = (int) $this->input('year', (int) date('Y'));
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $this->model->all($limit, $offset, [], $search),
            'total' => $this->model->count([], $search),
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->resolveIndexFields(),
            'csrf' => Csrf::token(),
            'leaveBalances' => (new HrService())->leaveBalancesSummary($companyId, $year),
            'balanceYear' => $year,
        ]), $this->layout());
    }

    public function create(): void
    {
        HrService::bootstrapTenant();
        parent::create();
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        if (empty($data['days']) && !empty($data['start_date']) && !empty($data['end_date'])) {
            $start = strtotime((string) $data['start_date']);
            $end = strtotime((string) $data['end_date']);
            if ($start !== false && $end !== false && $end >= $start) {
                $data['days'] = (int) round(($end - $start) / 86400) + 1;
            }
        }
        return $data;
    }

    public function approve(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HrService())->approveLeave($id, (int) (SessionManager::get('rateb_user_id') ?? 0));
            (new AuditService())->log('approve', $this->entityName, $id);
            SessionManager::flash('success', __('leave_approved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function reject(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HrService())->rejectLeave($id, (int) (SessionManager::get('rateb_user_id') ?? 0));
            (new AuditService())->log('reject', $this->entityName, $id);
            SessionManager::flash('success', __('leave_rejected'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrPayrollController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\PayrollPeriod();
        $this->viewPrefix = 'company/hr/payroll';
        $this->routePrefix = rateb_app_route('hr/payroll');
        $this->entityName = 'hr_payroll';
        $this->permissionResource = 'hr-payroll';
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'period_year', 'label' => 'period_year'],
            ['name' => 'period_month', 'label' => 'period_month'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'period_year', 'label' => 'period_year', 'type' => 'number', 'min' => '2020', 'max' => '2100', 'required' => true],
            ['name' => 'period_month', 'label' => 'period_month', 'type' => 'number', 'min' => '1', 'max' => '12', 'required' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    public function create(): void
    {
        $this->guardManage();
        $now = new \DateTimeImmutable('now');
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => [
                'period_year' => (int) $now->format('Y'),
                'period_month' => (int) $now->format('n'),
            ],
        ]), $this->layout());
    }

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));
        $items = $this->model->all($limit, $offset, [], $search);
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count([], $search),
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'canManage' => function_exists('rateb_can_manage_entity') ? rateb_can_manage_entity('hr-payroll') : true,
        ]), $this->layout());
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $data['status'] = 'draft';
        return $data;
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $period = $this->model->find($id);
        if (!$period) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $lines = (new \Rateb\App\Models\PayrollLine())->query(
            "SELECT pl.*, e.name AS employee_name, e.employee_code
             FROM rateb_payroll_lines pl
             JOIN rateb_employees e ON e.id = pl.employee_id
             WHERE pl.period_id = :pid
             ORDER BY e.name ASC",
            ['pid' => $id]
        );
        $this->view($this->viewPrefix . '/show', [
            'title' => __('hr_payroll'),
            'period' => $period,
            'lines' => $lines,
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'canManage' => function_exists('rateb_can_manage_entity') ? rateb_can_manage_entity('hr-payroll') : true,
        ], $this->layout());
    }

    public function generate(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $count = (new HrService())->generatePayrollLines($id);
            SessionManager::flash('success', __('payroll_generated', ['count' => $count]));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id));
    }

    public function export(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $period = $this->model->find($id);
        if (!$period) {
            http_response_code(404);
            echo '404';
            return;
        }
        $lines = (new \Rateb\App\Models\PayrollLine())->query(
            "SELECT pl.*, e.name AS employee_name, e.employee_code
             FROM rateb_payroll_lines pl
             JOIN rateb_employees e ON e.id = pl.employee_id
             WHERE pl.period_id = :pid ORDER BY e.name ASC",
            ['pid' => $id]
        );
        $exportRows = [];
        foreach ($lines as $line) {
            $exportRows[] = [
                'employee_code' => $line['employee_code'] ?? '',
                'employee_name' => $line['employee_name'] ?? '',
                'basic_salary' => $line['basic_salary'] ?? 0,
                'allowances' => $line['allowances'] ?? 0,
                'deductions' => $line['deductions'] ?? 0,
                'net_salary' => $line['net_salary'] ?? 0,
            ];
        }
        $title = __('hr_payroll') . ' ' . ($period['period_year'] ?? '') . '/' . ($period['period_month'] ?? '');
        \Rateb\App\Controllers\Shared\ExportController::send('payroll_' . $id, [
            ['name' => 'employee_code', 'label' => __('employee_code')],
            ['name' => 'employee_name', 'label' => __('name')],
            ['name' => 'basic_salary', 'label' => __('basic_salary')],
            ['name' => 'allowances', 'label' => __('allowances')],
            ['name' => 'deductions', 'label' => __('deductions')],
            ['name' => 'net_salary', 'label' => __('net_salary')],
        ], $exportRows, $title, 'hr-payroll');
    }

    public function payslip(array $params): void
    {
        $periodId = (int) ($params['id'] ?? 0);
        $lineId = (int) ($params['lineId'] ?? 0);
        $period = $this->model->find($periodId);
        $line = (new \Rateb\App\Models\PayrollLine())->queryOne(
            "SELECT pl.*, e.name AS employee_name, e.employee_code, e.job_title, e.national_id
             FROM rateb_payroll_lines pl
             JOIN rateb_employees e ON e.id = pl.employee_id
             WHERE pl.id = :lid AND pl.period_id = :pid LIMIT 1",
            ['lid' => $lineId, 'pid' => $periodId]
        );
        if (!$period || !$line) {
            http_response_code(404);
            echo '404';
            return;
        }
        $this->view($this->viewPrefix . '/payslip', [
            'period' => $period,
            'line' => $line,
            'title' => __('payslip'),
        ], 'print');
    }

    public function approve(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HrService())->approvePayroll($id);
            SessionManager::flash('success', __('payroll_approved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id));
    }

    public function post(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HrService())->postPayroll($id);
            SessionManager::flash('success', __('payroll_posted'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrLeaveTypesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\LeaveType();
        $this->viewPrefix = 'company/hr/leave-types';
        $this->routePrefix = rateb_app_route('hr/leave-types');
        $this->entityName = 'leave_types';
        $this->permissionResource = 'hr-leaves';
        $this->indexFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'paid', 'label' => 'paid_leave'],
            ['name' => 'days_per_year', 'label' => 'days_per_year'],
            ['name' => 'status', 'label' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'paid', 'label' => 'paid_leave', 'type' => 'select', 'lookup' => 'yes_no', 'translate_options' => true],
            ['name' => 'days_per_year', 'label' => 'days_per_year', 'type' => 'number', 'step' => '0.5', 'min' => '0'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    public function index(): void
    {
        HrService::bootstrapTenant();
        parent::index();
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrReportsController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $year = max(2020, (int) $this->input('year', (int) date('Y')));
        $month = max(1, min(12, (int) $this->input('month', (int) date('n'))));
        $report = (new HrService())->monthlyReport($companyId, $year, $month);
        $this->view('company/hr/reports', [
            'title' => __('hr_reports'),
            'year' => $year,
            'month' => $month,
            'attendance' => $report['attendance'],
            'payroll' => $report['payroll'],
            'exportRoute' => rateb_app_url('hr/reports/export') . '?year=' . $year . '&month=' . $month,
            'exportEnabled' => function_exists('rateb_can_export_entity') ? rateb_can_export_entity('hr') : true,
        ], 'main');
    }

    public function export(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $year = max(2020, (int) $this->input('year', (int) date('Y')));
        $month = max(1, min(12, (int) $this->input('month', (int) date('n'))));
        $report = (new HrService())->monthlyReport($companyId, $year, $month);
        $rows = [];
        foreach ($report['attendance'] as $row) {
            $rows[] = [
                'employee_code' => $row['employee_code'] ?? '',
                'name' => $row['name'] ?? '',
                'present_days' => $row['present_days'] ?? 0,
                'absent_days' => $row['absent_days'] ?? 0,
                'leave_days' => $row['leave_days'] ?? 0,
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('hr_report', [
            ['name' => 'employee_code', 'label' => __('employee_code')],
            ['name' => 'name', 'label' => __('name')],
            ['name' => 'present_days', 'label' => __('present_days')],
            ['name' => 'absent_days', 'label' => __('absent_days')],
            ['name' => 'leave_days', 'label' => __('leave_days')],
        ], $rows, __('hr_reports'), 'hr');
    }
}
