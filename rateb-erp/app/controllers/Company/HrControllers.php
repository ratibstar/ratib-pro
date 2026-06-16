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
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $stats = (new HrService())->dashboardStats($companyId);
        $this->view('company/hr/dashboard', [
            'title' => __('human_resources'),
            'stats' => $stats,
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
        parent::index();
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
