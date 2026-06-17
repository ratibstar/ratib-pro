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

final class HrHolidaysController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrHoliday();
        $this->viewPrefix = 'company/hr/holidays';
        $this->routePrefix = rateb_app_route('hr/holidays');
        $this->entityName = 'hr_holidays';
        $this->permissionResource = 'hr-leaves';
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'holiday_date', 'label' => 'holiday_date'],
            ['name' => 'is_recurring', 'label' => 'recurring', 'type' => 'fk', 'lookup' => 'yes_no'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'holiday_date', 'label' => 'holiday_date', 'type' => 'date', 'required' => true],
            ['name' => 'is_recurring', 'label' => 'recurring', 'type' => 'select', 'lookup' => 'yes_no', 'translate_options' => true],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrWorkplacesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrWorkplace();
        $this->viewPrefix = 'company/hr/workplaces';
        $this->routePrefix = rateb_app_route('hr/workplaces');
        $this->entityName = 'hr_workplaces';
        $this->permissionResource = 'hr-attendance';
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'address', 'label' => 'address'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'address', 'label' => 'address', 'type' => 'text', 'col' => 'col-12'],
            ['name' => 'radius_meters', 'label' => 'radius_meters', 'type' => 'number', 'min' => '10', 'step' => '1'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrPermissionRequestsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrPermissionRequest();
        $this->viewPrefix = 'company/hr/permission-requests';
        $this->routePrefix = rateb_app_route('hr/permission-requests');
        $this->entityName = 'hr_permission_requests';
        $this->permissionResource = 'hr-attendance';
        $this->tenantForeignKeys = ['employee_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'permission_date', 'label' => 'permission_date'],
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'time_from', 'label' => 'time_from'],
            ['name' => 'time_to', 'label' => 'time_to'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'permission_date', 'label' => 'permission_date', 'type' => 'date', 'required' => true],
            ['name' => 'time_from', 'label' => 'time_from', 'type' => 'text', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num', 'placeholder' => '09:00']],
            ['name' => 'time_to', 'label' => 'time_to', 'type' => 'text', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num', 'placeholder' => '11:00']],
            ['name' => 'reason', 'label' => 'reason', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'leave_request_statuses', 'translate_options' => true],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        return $data;
    }

    public function approve(array $params): void
    {
        $this->workflowAction($params, 'approve', 'approved', 'permission_approved');
    }

    public function reject(array $params): void
    {
        $this->workflowAction($params, 'reject', 'rejected', 'permission_rejected');
    }

    private function workflowAction(array $params, string $action, string $status, string $flashKey): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row || (string) ($row['status'] ?? '') !== 'pending') {
            SessionManager::flash('error', __('leave_not_pending'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->model->update($id, [
            'status' => $status,
            'approved_by' => (int) (SessionManager::get('rateb_user_id') ?? 0),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditService())->log($action, $this->entityName, $id);
        SessionManager::flash('success', __($flashKey));
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrLoanTypesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrLoanType();
        $this->viewPrefix = 'company/hr/loan-types';
        $this->routePrefix = rateb_app_route('hr/loan-types');
        $this->entityName = 'hr_loan_types';
        $this->permissionResource = 'hr-payroll';
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'max_amount', 'label' => 'max_amount'],
            ['name' => 'max_installments', 'label' => 'max_installments'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'max_amount', 'label' => 'max_amount', 'type' => 'number', 'step' => '0.01', 'min' => '0'],
            ['name' => 'max_installments', 'label' => 'max_installments', 'type' => 'number', 'min' => '1', 'step' => '1'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrLoansController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrLoan();
        $this->viewPrefix = 'company/hr/loans';
        $this->routePrefix = rateb_app_route('hr/loans');
        $this->entityName = 'hr_loans_list';
        $this->permissionResource = 'hr-payroll';
        $this->tenantForeignKeys = ['employee_id', 'loan_type_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'loan_code', 'label' => 'loan_code'],
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'loan_type_id', 'label' => 'loan_type', 'type' => 'fk', 'lookup' => 'loan_types'],
            ['name' => 'principal', 'label' => 'principal'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'loan_type_id', 'label' => 'loan_type', 'type' => 'fk', 'lookup' => 'loan_types', 'required' => true],
            ['name' => 'principal', 'label' => 'principal', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true],
            ['name' => 'installments_count', 'label' => 'installments_count', 'type' => 'number', 'min' => '1', 'step' => '1', 'required' => true],
            ['name' => 'installment_amount', 'label' => 'installment_amount', 'type' => 'number', 'step' => '0.01', 'min' => '0'],
            ['name' => 'start_date', 'label' => 'start_date', 'type' => 'date', 'required' => true],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'loan_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, DocumentCodeService::PREFIX_LOAN, 'loan_code');
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }
        $principal = (float) ($data['principal'] ?? 0);
        $count = max(1, (int) ($data['installments_count'] ?? 1));
        if ($principal > 0 && (float) ($data['installment_amount'] ?? 0) <= 0) {
            $data['installment_amount'] = round($principal / $count, 2);
        }
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrPayrollComponentsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrPayrollComponent();
        $this->viewPrefix = 'company/hr/payroll-components';
        $this->routePrefix = rateb_app_route('hr/payroll/components');
        $this->entityName = 'hr_payroll_components';
        $this->permissionResource = 'hr-payroll';
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'component_type', 'label' => 'component_type', 'type' => 'status'],
            ['name' => 'default_value', 'label' => 'default_value'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text', 'required' => true],
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'component_type', 'label' => 'component_type', 'type' => 'select', 'lookup' => 'payroll_component_types', 'translate_options' => true, 'required' => true],
            ['name' => 'calc_type', 'label' => 'calc_type', 'type' => 'select', 'lookup' => 'payroll_calc_types', 'translate_options' => true, 'required' => true],
            ['name' => 'default_value', 'label' => 'default_value', 'type' => 'number', 'step' => '0.01', 'min' => '0'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrPayrollStructuresController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrPayrollStructure();
        $this->viewPrefix = 'company/hr/payroll-structure';
        $this->routePrefix = rateb_app_route('hr/payroll/structure');
        $this->entityName = 'hr_payroll_structure';
        $this->permissionResource = 'hr-payroll';
        $this->tenantForeignKeys = ['employee_id', 'component_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'component_id', 'label' => 'payroll_component', 'type' => 'fk', 'lookup' => 'hr_payroll_components'],
            ['name' => 'value', 'label' => 'value'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'component_id', 'label' => 'payroll_component', 'type' => 'fk', 'lookup' => 'hr_payroll_components', 'required' => true],
            ['name' => 'value', 'label' => 'value', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrEmployeeDocumentsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrDocument();
        $this->viewPrefix = 'company/hr/documents';
        $this->routePrefix = rateb_app_route('hr/documents');
        $this->entityName = 'hr_documents_manage';
        $this->permissionResource = 'hr-employees';
        $this->tenantForeignKeys = ['employee_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'title', 'label' => 'title'],
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'doc_type', 'label' => 'doc_type', 'type' => 'status'],
            ['name' => 'issue_date', 'label' => 'issue_date'],
            ['name' => 'expiry_date', 'label' => 'expiry_date'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'title', 'label' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'doc_type', 'label' => 'doc_type', 'type' => 'select', 'lookup' => 'hr_document_types', 'translate_options' => true, 'required' => true],
            ['name' => 'issue_date', 'label' => 'issue_date', 'type' => 'date'],
            ['name' => 'expiry_date', 'label' => 'expiry_date', 'type' => 'date'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrFleetController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrFleetVehicle();
        $this->viewPrefix = 'company/hr/fleet';
        $this->routePrefix = rateb_app_route('hr/fleet');
        $this->entityName = 'hr_fleet_manage';
        $this->permissionResource = 'hr-employees';
        $this->tenantForeignKeys = ['assigned_employee_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'plate_number', 'label' => 'plate_number'],
            ['name' => 'brand', 'label' => 'brand'],
            ['name' => 'model', 'label' => 'model'],
            ['name' => 'assigned_employee_id', 'label' => 'assigned_employee', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'plate_number', 'label' => 'plate_number', 'type' => 'text', 'required' => true],
            ['name' => 'brand', 'label' => 'brand', 'type' => 'text'],
            ['name' => 'model', 'label' => 'model', 'type' => 'text'],
            ['name' => 'model_year', 'label' => 'model_year', 'type' => 'number', 'min' => '1990', 'max' => '2100'],
            ['name' => 'assigned_employee_id', 'label' => 'assigned_employee', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'fleet_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrEmployeeRequestsController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrEmployeeRequest();
        $this->viewPrefix = 'company/hr/requests';
        $this->routePrefix = rateb_app_route('hr/requests');
        $this->entityName = 'hr_employee_requests';
        $this->permissionResource = 'hr-leaves';
        $this->tenantForeignKeys = ['employee_id'];
        $this->filesEnabled = false;
        $this->indexFields = [
            ['name' => 'request_no', 'label' => 'request_no'],
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees'],
            ['name' => 'request_type', 'label' => 'request_type', 'type' => 'status'],
            ['name' => 'request_date', 'label' => 'request_date'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
            ['name' => 'processed_at', 'label' => 'processed_at'],
        ];
        $this->fields = [
            ['name' => 'employee_id', 'label' => 'hr_employees', 'type' => 'fk', 'lookup' => 'employees', 'required' => true],
            ['name' => 'request_type', 'label' => 'request_type', 'type' => 'select', 'lookup' => 'employee_request_types', 'translate_options' => true, 'required' => true],
            ['name' => 'request_date', 'label' => 'request_date', 'type' => 'date', 'required' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3],
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
        ]), $this->layout());
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, DocumentCodeService::PREFIX_HR_REQUEST, 'request_no');
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        if (empty($data['request_date'])) {
            $data['request_date'] = date('Y-m-d');
        }
        return $data;
    }

    public function approve(array $params): void
    {
        $this->workflowAction($params, 'approve', 'approved', 'request_approved');
    }

    public function reject(array $params): void
    {
        $this->workflowAction($params, 'reject', 'rejected', 'request_rejected');
    }

    private function workflowAction(array $params, string $action, string $status, string $flashKey): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row || (string) ($row['status'] ?? '') !== 'pending') {
            SessionManager::flash('error', __('leave_not_pending'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->model->update($id, [
            'status' => $status,
            'processed_by' => (int) (SessionManager::get('rateb_user_id') ?? 0),
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditService())->log($action, $this->entityName, $id);
        SessionManager::flash('success', __($flashKey));
        $this->redirect(rateb_url($this->routePrefix));
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrAttendanceBulkController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $date = trim((string) $this->input('date', date('Y-m-d')));
        $employees = (new \Rateb\App\Models\Employee())->query(
            "SELECT id, employee_code, name FROM rateb_employees
             WHERE company_id = :cid AND status = 'active' ORDER BY name ASC",
            ['cid' => $companyId]
        );
        $existing = [];
        foreach ((new \Rateb\App\Models\AttendanceRecord())->query(
            "SELECT employee_id, check_in, check_out, status FROM rateb_attendance_records
             WHERE company_id = :cid AND attendance_date = :d",
            ['cid' => $companyId, 'd' => $date]
        ) as $row) {
            $existing[(int) ($row['employee_id'] ?? 0)] = $row;
        }
        $this->view('company/hr/attendance/bulk', [
            'title' => __('hr_attendance_bulk'),
            'date' => $date,
            'employees' => $employees,
            'existing' => $existing,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/attendance/bulk'),
            'canManage' => function_exists('rateb_can_manage_entity') ? rateb_can_manage_entity('hr-attendance') : true,
        ], 'main');
    }

    public function store(): void
    {
        if (!function_exists('rateb_can_manage_entity') || !rateb_can_manage_entity('hr-attendance')) {
            http_response_code(403);
            echo '403';
            return;
        }
        if (!Csrf::validate((string) $this->input('_csrf', ''))) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hr/attendance/bulk')));
        }
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $date = trim((string) $this->input('attendance_date', ''));
        if ($date === '') {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hr/attendance/bulk')));
        }
        $present = (array) $this->input('present', []);
        $checkIn = (array) $this->input('check_in', []);
        $checkOut = (array) $this->input('check_out', []);
        $model = new \Rateb\App\Models\AttendanceRecord();
        $saved = 0;
        foreach ($present as $employeeId => $on) {
            if (!$on) {
                continue;
            }
            $eid = (int) $employeeId;
            if ($eid < 1) {
                continue;
            }
            $payload = [
                'company_id' => $companyId,
                'employee_id' => $eid,
                'attendance_date' => $date,
                'check_in' => trim((string) ($checkIn[$employeeId] ?? '09:00')) ?: '09:00:00',
                'check_out' => trim((string) ($checkOut[$employeeId] ?? '17:00')) ?: '17:00:00',
                'status' => 'present',
            ];
            $existing = $model->queryOne(
                "SELECT id FROM rateb_attendance_records
                 WHERE company_id = :cid AND employee_id = :eid AND attendance_date = :d LIMIT 1",
                ['cid' => $companyId, 'eid' => $eid, 'd' => $date]
            );
            if ($existing) {
                $model->update((int) $existing['id'], $payload);
            } else {
                $model->create($payload);
            }
            $saved++;
        }
        SessionManager::flash('success', __('attendance_bulk_saved', ['count' => $saved]));
        $this->redirect(rateb_url(rateb_app_route('hr/attendance/bulk')) . '?date=' . urlencode($date));
    }
}
