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

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $this->ensureTenantCompanyForWrite($data);
        $id = $this->model->create($data);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        $this->syncHolidayIfActive($data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        $this->model->update($id, $data);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        $this->syncHolidayIfActive($data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data */
    private function syncHolidayIfActive(array $data): void
    {
        if ((string) ($data['status'] ?? 'active') !== 'active') {
            return;
        }
        $companyId = (int) ($data['company_id'] ?? 0);
        $date = (string) ($data['holiday_date'] ?? '');
        if ($companyId < 1 || $date === '') {
            return;
        }
        $count = (new HrService())->syncHolidayAttendance($companyId, $date, (string) ($data['name'] ?? ''));
        if ($count > 0) {
            SessionManager::flash('success', __('holiday_attendance_synced', ['count' => $count]));
        }
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
            ['name' => 'time_from', 'label' => 'time_from', 'type' => 'select', 'lookup' => 'hr_time_slots', 'default' => '09:00', 'required' => true],
            ['name' => 'time_to', 'label' => 'time_to', 'type' => 'select', 'lookup' => 'hr_time_slots', 'default' => '11:00', 'required' => true],
            ['name' => 'reason', 'label' => 'reason', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    public function index(): void
    {
        HrService::bootstrapTenant();
        parent::index();
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

    /** @return array<string, mixed>|null */
    private function fleetDetail(int $id): ?array
    {
        HrService::bootstrapTenant();
        return (new HrService())->fleetVehicleDetail($id);
    }

    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $detail = $this->fleetDetail($id);
        if ($detail === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $vehicle = $detail['vehicle'];
        $this->view($this->viewPrefix . '/show', array_merge($detail, [
            'title' => __('hr_fleet') . ' — ' . (string) ($vehicle['plate_number'] ?? ''),
            'routePrefix' => $this->routePrefix,
            'employeeRoutePrefix' => rateb_app_route('hr/employees'),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('hr-employees'),
        ]), $this->layout());
    }

    public function print(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $detail = $this->fleetDetail($id);
        if ($detail === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $vehicle = $detail['vehicle'];
        $this->view($this->viewPrefix . '/print', array_merge($detail, [
            'title' => __('print') . ' — ' . (string) ($vehicle['plate_number'] ?? ''),
        ]), 'print');
    }

    public function employeeReceipt(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $detail = $this->fleetDetail($id);
        if ($detail === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $vehicle = $detail['vehicle'];
        $this->view($this->viewPrefix . '/receipt', array_merge($detail, [
            'title' => __('fleet_employee_receipt') . ' — ' . (string) ($vehicle['plate_number'] ?? ''),
            'receipt_date' => date('Y-m-d'),
        ]), 'print');
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
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
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
            'bulkEnabled' => $this->bulkEnabled,
            'actionsEnabled' => $this->actionsEnabled,
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

    protected function afterSuccessfulStore(int $id, array $data): void
    {
        $type = (string) ($data['request_type'] ?? '');
        if (\Rateb\App\Services\HrLetterIssueService::isLetterType($type)
            || in_array($type, ['inquiry', 'complaint', 'other'], true)
        ) {
            try {
                $companyId = (int) ($data['company_id'] ?? rateb_resolve_ops_company_id());
                if ($companyId > 0 && $id > 0) {
                    \Rateb\App\Services\ApprovalOversightService::notifyPendingSubmission(
                        $companyId,
                        'hr_request',
                        'hr_request #' . $id,
                        $id
                    );
                }
            } catch (\Throwable $e) {
                // Non-blocking — request already stored.
            }
        }
        (new AuditService())->log('hr_letter_request_create', 'hr_employee_request', $id, [
            'request_type' => $type,
            'employee_id' => (int) ($data['employee_id'] ?? 0),
        ]);
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

/**
 * Phase L — Letters workspace (issue / download PDF after Matrix/Oversight approve).
 */
final class HrLettersController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $status = trim((string) $this->input('status', 'all'));
        $svc = new \Rateb\App\Services\HrLetterIssueService();
        $items = $companyId > 0 ? $svc->listLetters($companyId, $status === 'all' ? null : $status) : [];
        $this->view('company/hr/letters/index', [
            'title' => __('hr_letters'),
            'companyId' => $companyId,
            'items' => $items,
            'statusFilter' => $status,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/letters'),
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-leaves.manage') || rateb_can('hr-employees.manage')),
        ], 'main');
    }

    public function issue(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/letters')));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $redirect = rateb_url(rateb_app_route('hr/letters'));
        try {
            (new \Rateb\App\Services\HrLetterIssueService())->issue($companyId, $id);
            SessionManager::flash('success', __('hr_letter_issued'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect($redirect);
    }

    public function download(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        try {
            (new \Rateb\App\Services\HrLetterIssueService())->download($companyId, $id);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
            $this->redirect(rateb_url(rateb_app_route('hr/letters')));
        }
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
            $ownedEmployee = $model->queryOne(
                'SELECT id FROM rateb_employees WHERE id = :eid AND company_id = :cid LIMIT 1',
                ['eid' => $eid, 'cid' => $companyId]
            );
            if (!$ownedEmployee) {
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
            $existing = (new HrService())->findAttendanceByEmployeeDate($companyId, $eid, $date);
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

/** Phase M — HR employee decisions (approve via inbox/Matrix; execute once). */
final class HrDecisionsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $status = trim((string) $this->input('status', 'all'));
        $type = trim((string) $this->input('type', 'all'));
        $svc = new \Rateb\App\Services\HrDecisionService();
        $items = $companyId > 0 ? $svc->list($companyId, $status === 'all' ? null : $status, $type === 'all' ? null : $type) : [];
        $this->view('company/hr/decisions/index', [
            'title' => __('hr_decisions'),
            'companyId' => $companyId,
            'items' => $items,
            'statusFilter' => $status,
            'typeFilter' => $type,
            'decisionTypes' => \Rateb\App\Services\HrDecisionService::TYPES,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/decisions'),
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage')),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $employees = $companyId > 0
            ? (new \Rateb\App\Models\Employee())->query(
                "SELECT id, employee_code, name, status FROM rateb_employees
                 WHERE company_id = :cid ORDER BY name ASC LIMIT 500",
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/hr/decisions/create', [
            'title' => __('hr_decision_new'),
            'companyId' => $companyId,
            'employees' => $employees,
            'decisionTypes' => \Rateb\App\Services\HrDecisionService::TYPES,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/decisions'),
        ], 'main');
    }

    public function store(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/decisions/create')));
        }
        $companyId = rateb_resolve_ops_company_id();
        try {
            $result = (new \Rateb\App\Services\HrDecisionService())->create($companyId, [
                'employee_id' => (int) $this->input('employee_id', 0),
                'decision_type' => (string) $this->input('decision_type', ''),
                'effective_date' => (string) $this->input('effective_date', ''),
                'reason' => (string) $this->input('reason', ''),
                'new_salary_base' => $this->input('new_salary_base', null),
                'new_job_title' => (string) $this->input('new_job_title', ''),
                'new_department_id' => (int) $this->input('new_department_id', 0),
                'new_branch_id' => (int) $this->input('new_branch_id', 0),
                'deduction_days' => $this->input('deduction_days', null),
                'deduction_amount' => $this->input('deduction_amount', null),
                'note' => (string) $this->input('note', ''),
            ]);
            SessionManager::flash('success', __('hr_decision_created') . ' ' . ($result['decision_no'] ?? ''));
            $this->redirect(rateb_url(rateb_app_route('hr/decisions')));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('save_failed'));
            $this->redirect(rateb_url(rateb_app_route('hr/decisions/create')));
        }
    }

    public function execute(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/decisions')));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        try {
            $out = (new \Rateb\App\Services\HrDecisionService())->execute($companyId, $id);
            SessionManager::flash(
                'success',
                $out['already'] ? __('hr_decision_already_executed') : __('hr_decision_executed')
            );
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect(rateb_url(rateb_app_route('hr/decisions')));
    }
}

/** Phase M — Disciplinary linked to rateb_employees. */
final class HrDisciplinaryController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $employeeId = (int) $this->input('employee_id', 0);
        $svc = new \Rateb\App\Services\HrDisciplinaryService();
        $items = $companyId > 0
            ? $svc->list($companyId, $employeeId > 0 ? $employeeId : null)
            : [];
        $this->view('company/hr/disciplinary/index', [
            'title' => __('hr_disciplinary'),
            'companyId' => $companyId,
            'items' => $items,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/disciplinary'),
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage')),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $employees = $companyId > 0
            ? (new \Rateb\App\Models\Employee())->query(
                "SELECT id, employee_code, name FROM rateb_employees
                 WHERE company_id = :cid ORDER BY name ASC LIMIT 500",
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/hr/disciplinary/create', [
            'title' => __('hr_disciplinary_new'),
            'companyId' => $companyId,
            'employees' => $employees,
            'actionTypes' => \Rateb\App\Services\HrDisciplinaryService::ACTION_TYPES,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/disciplinary'),
            'prefillEmployeeId' => (int) $this->input('employee_id', 0),
        ], 'main');
    }

    public function store(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/disciplinary/create')));
        }
        $companyId = rateb_resolve_ops_company_id();
        try {
            $result = (new \Rateb\App\Services\HrDisciplinaryService())->create($companyId, [
                'employee_id' => (int) $this->input('employee_id', 0),
                'action_type' => (string) $this->input('action_type', 'warning'),
                'title' => (string) $this->input('title', ''),
                'action_date' => (string) $this->input('action_date', ''),
                'description' => (string) $this->input('description', ''),
                'notes' => (string) $this->input('notes', ''),
            ]);
            SessionManager::flash('success', __('hr_disciplinary_created') . ' ' . ($result['code'] ?? ''));
            $this->redirect(rateb_url(rateb_app_route('hr/disciplinary')));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('save_failed'));
            $this->redirect(rateb_url(rateb_app_route('hr/disciplinary/create')));
        }
    }
}

/** Phase O — Organization structure (departments / positions / employees). */
final class HrOrganizationController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $filters = [
            'department_id' => (int) $this->input('department_id', 0),
            'job_title_id' => (int) $this->input('job_title_id', 0),
            'status' => (string) $this->input('status', ''),
        ];
        $org = new \Rateb\App\Services\HrOrganizationService();
        $data = $companyId > 0 ? $org->structure($companyId, $filters) : ['departments' => [], 'unassigned' => ['employees' => [], 'employee_count' => 0], 'totals' => ['employees' => 0, 'departments' => 0]];
        $this->view('company/hr/organization/index', [
            'title' => __('hr_organization'),
            'companyId' => $companyId,
            'data' => $data,
            'filters' => $filters,
            'departments' => $companyId > 0 ? $org->listDepartments($companyId) : [],
            'jobTitles' => $companyId > 0 ? $org->listJobTitles($companyId) : [],
            'routePrefix' => rateb_app_route('hr/organization'),
        ], 'main');
    }
}

/** Phase O — Succession planning. */
final class HrSuccessionController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $svc = new \Rateb\App\Services\HrSuccessionService();
        $this->view('company/hr/succession/index', [
            'title' => __('hr_succession'),
            'companyId' => $companyId,
            'schemaReady' => $svc->schemaReady(),
            'items' => $companyId > 0 ? $svc->listPositions($companyId) : [],
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/succession'),
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage')),
        ], 'main');
    }

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $org = new \Rateb\App\Services\HrOrganizationService();
        $employees = $companyId > 0
            ? (new \Rateb\App\Models\Employee())->query(
                "SELECT id, name, employee_code FROM rateb_employees WHERE company_id = :cid ORDER BY name ASC LIMIT 500",
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/hr/succession/create', [
            'title' => __('hr_succession_new_position'),
            'companyId' => $companyId,
            'departments' => $companyId > 0 ? $org->listDepartments($companyId) : [],
            'jobTitles' => $companyId > 0 ? $org->listJobTitles($companyId) : [],
            'employees' => $employees,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/succession'),
        ], 'main');
    }

    public function store(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/succession/create')));
        }
        $companyId = rateb_resolve_ops_company_id();
        try {
            $result = (new \Rateb\App\Services\HrSuccessionService())->createPosition($companyId, [
                'title' => (string) $this->input('title', ''),
                'code' => (string) $this->input('code', ''),
                'department_id' => (int) $this->input('department_id', 0),
                'job_title_id' => (int) $this->input('job_title_id', 0),
                'current_employee_id' => (int) $this->input('current_employee_id', 0),
                'skill_gap_notes' => (string) $this->input('skill_gap_notes', ''),
                'is_critical' => 1,
            ]);
            SessionManager::flash('success', __('hr_succession_position_created') . ' ' . ($result['code'] ?? ''));
            $this->redirect(rateb_url(rateb_app_route('hr/succession/' . $result['id'])));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('save_failed'));
            $this->redirect(rateb_url(rateb_app_route('hr/succession/create')));
        }
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $svc = new \Rateb\App\Services\HrSuccessionService();
        $pos = $companyId > 0 ? $svc->findPosition($companyId, $id) : null;
        if ($pos === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $employees = (new \Rateb\App\Models\Employee())->query(
            "SELECT id, name, employee_code FROM rateb_employees
             WHERE company_id = :cid AND status = 'active' ORDER BY name ASC LIMIT 500",
            ['cid' => $companyId]
        );
        $this->view('company/hr/succession/show', [
            'title' => (string) ($pos['title'] ?? __('hr_succession')),
            'companyId' => $companyId,
            'position' => $pos,
            'candidates' => $svc->listCandidates($companyId, $id),
            'employees' => $employees,
            'readiness' => \Rateb\App\Services\HrSuccessionService::READINESS,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/succession'),
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage')),
        ], 'main');
    }

    public function storeCandidate(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/succession')));
        }
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        try {
            (new \Rateb\App\Services\HrSuccessionService())->addCandidate($companyId, $id, [
                'employee_id' => (int) $this->input('employee_id', 0),
                'readiness' => (string) $this->input('readiness', 'developing'),
                'rank_order' => (int) $this->input('rank_order', 1),
                'skill_gap_notes' => (string) $this->input('skill_gap_notes', ''),
                'notes' => (string) $this->input('notes', ''),
            ]);
            SessionManager::flash('success', __('hr_succession_candidate_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('save_failed'));
        }
        $this->redirect(rateb_url(rateb_app_route('hr/succession/' . $id)));
    }
}

/** Phase O — Analytics + reports hub. */
final class HrAnalyticsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $filters = $this->filtersFromInput();
        $canViewSalary = $this->canViewSalary();
        $org = new \Rateb\App\Services\HrOrganizationService();
        $snap = $companyId > 0
            ? (new \Rateb\App\Services\HrAnalyticsService())->snapshot($companyId, $filters, $canViewSalary)
            : [];
        $this->view('company/hr/analytics/index', [
            'title' => __('hr_analytics'),
            'companyId' => $companyId,
            'snapshot' => $snap,
            'filters' => $filters,
            'departments' => $companyId > 0 ? $org->listDepartments($companyId) : [],
            'jobTitles' => $companyId > 0 ? $org->listJobTitles($companyId) : [],
            'canViewSalary' => $canViewSalary,
            'routePrefix' => rateb_app_route('hr/analytics'),
        ], 'main');
    }

    public function reports(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $type = trim((string) $this->input('type', 'employees'));
        $filters = $this->filtersFromInput();
        $canViewSalary = $this->canViewSalary();
        $svc = new \Rateb\App\Services\HrAnalyticsService();
        $org = new \Rateb\App\Services\HrOrganizationService();
        $rows = [];
        if ($companyId > 0) {
            $rows = match ($type) {
                'attendance' => $svc->attendanceReportRows($companyId, $filters),
                'leaves' => $svc->leaveReportRows($companyId, $filters),
                'payroll' => $svc->payrollSummaryRows($companyId, $filters, $canViewSalary),
                'contracts' => $svc->contractsExpiryRows($companyId, 60),
                'recruitment' => $svc->recruitmentSummary($companyId),
                default => $svc->employeeReportRows($companyId, $filters),
            };
        }
        $exportRoute = rateb_app_url('hr/reports-hub/export') . '?' . http_build_query(array_merge($filters, ['type' => $type]));
        $this->view('company/hr/analytics/reports', [
            'title' => __('hr_reports_hub'),
            'companyId' => $companyId,
            'type' => $type,
            'rows' => $rows,
            'filters' => $filters,
            'departments' => $companyId > 0 ? $org->listDepartments($companyId) : [],
            'jobTitles' => $companyId > 0 ? $org->listJobTitles($companyId) : [],
            'canViewSalary' => $canViewSalary,
            'exportRoute' => $exportRoute,
            'exportEnabled' => function_exists('rateb_can_export_entity') ? rateb_can_export_entity('hr') : true,
            'routePrefix' => rateb_app_route('hr/reports-hub'),
        ], 'main');
    }

    public function export(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = rateb_resolve_ops_company_id();
        $type = trim((string) $this->input('type', 'employees'));
        $filters = $this->filtersFromInput();
        $canViewSalary = $this->canViewSalary();
        $svc = new \Rateb\App\Services\HrAnalyticsService();
        $rows = match ($type) {
            'attendance' => $svc->attendanceReportRows($companyId, $filters),
            'leaves' => $svc->leaveReportRows($companyId, $filters),
            'payroll' => $svc->payrollSummaryRows($companyId, $filters, $canViewSalary),
            'contracts' => $svc->contractsExpiryRows($companyId, 60),
            'recruitment' => array_map(static fn ($r) => [
                'status' => $r['status'] ?? '',
                'count' => $r['count'] ?? 0,
            ], $svc->recruitmentSummary($companyId)),
            default => $svc->employeeReportRows($companyId, $filters),
        };
        $columns = match ($type) {
            'attendance' => [
                ['name' => 'employee_code', 'label' => __('employee_code')],
                ['name' => 'name', 'label' => __('name')],
                ['name' => 'present_days', 'label' => __('present_days')],
                ['name' => 'late_days', 'label' => __('hr_cc_late_today')],
                ['name' => 'absent_days', 'label' => __('absent_days')],
                ['name' => 'leave_days', 'label' => __('leave_days')],
            ],
            'leaves' => [
                ['name' => 'employee_code', 'label' => __('employee_code')],
                ['name' => 'name', 'label' => __('name')],
                ['name' => 'leave_type', 'label' => __('leave_type')],
                ['name' => 'status', 'label' => __('status')],
                ['name' => 'start_date', 'label' => __('start_date')],
                ['name' => 'end_date', 'label' => __('end_date')],
                ['name' => 'days', 'label' => __('days')],
            ],
            'payroll' => [
                ['name' => 'period_year', 'label' => __('period_year')],
                ['name' => 'period_month', 'label' => __('period_month')],
                ['name' => 'status', 'label' => __('status')],
                ['name' => 'line_count', 'label' => __('hr_o_line_count')],
                ['name' => 'gross_total', 'label' => __('hr_360_gross')],
                ['name' => 'net_total', 'label' => __('net_salary')],
            ],
            'contracts' => [
                ['name' => 'contract_no', 'label' => __('hr_contract_no')],
                ['name' => 'employee_code', 'label' => __('employee_code')],
                ['name' => 'employee_name', 'label' => __('name')],
                ['name' => 'end_date', 'label' => __('end_date')],
                ['name' => 'status', 'label' => __('status')],
            ],
            'recruitment' => [
                ['name' => 'status', 'label' => __('status')],
                ['name' => 'count', 'label' => __('hr_o_count')],
            ],
            default => [
                ['name' => 'employee_code', 'label' => __('employee_code')],
                ['name' => 'name', 'label' => __('name')],
                ['name' => 'department_name', 'label' => __('department')],
                ['name' => 'job_title_name', 'label' => __('job_title')],
                ['name' => 'status', 'label' => __('status')],
                ['name' => 'hire_date', 'label' => __('hire_date')],
            ],
        };
        if ($type === 'payroll' && !$canViewSalary) {
            $rows = [];
        }
        \Rateb\App\Controllers\Shared\ExportController::send(
            'hr_o_' . $type,
            $columns,
            $rows,
            __('hr_reports_hub') . ' — ' . $type,
            'hr'
        );
    }

    /** @return array{department_id:int,job_title_id:int,status:string,date_from:string,date_to:string} */
    private function filtersFromInput(): array
    {
        return [
            'department_id' => (int) $this->input('department_id', 0),
            'job_title_id' => (int) $this->input('job_title_id', 0),
            'status' => (string) $this->input('status', ''),
            'date_from' => (string) $this->input('date_from', date('Y-m-01')),
            'date_to' => (string) $this->input('date_to', date('Y-m-d')),
        ];
    }

    private function canViewSalary(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity('hr-payroll')) {
            return true;
        }
        if (function_exists('rateb_can_manage_entity') && rateb_can_manage_entity('hr-employees')) {
            return true;
        }

        return function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-payroll.view'));
    }
}

/** Phase P — ESS self-service portal (Admin shell; same ESS services / SoT). */
final class HrEssPortalController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $userId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $result = $companyId > 0 && $userId > 0
            ? (new \Rateb\App\Services\HrEss360Service())->simplified360($userId, $companyId)
            : ['status' => 401, 'body' => ['success' => false, 'code' => 'unauthorized', 'message' => 'Unauthorized']];
        $this->view('company/hr/ess/index', [
            'title' => __('hr_ess_portal'),
            'companyId' => $companyId,
            'payload' => (int) ($result['status'] ?? 0) === 200 ? ($result['body'] ?? []) : null,
            'error' => (int) ($result['status'] ?? 0) === 200 ? null : ($result['body']['message'] ?? __('employee_unbound')),
            'errorCode' => (int) ($result['status'] ?? 0) === 200 ? null : ($result['body']['code'] ?? ''),
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/ess'),
            'certificateTypes' => \Rateb\App\Services\HrLetterIssueService::LETTER_TYPES,
        ], 'main');
    }

    public function requestCertificate(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $userId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hr/ess')));
            return;
        }
        $result = (new \Rateb\App\Services\HrEssPhaseCService())->submitInquiry($userId, $companyId, [
            'request_type' => (string) $this->input('request_type', 'employment_certificate'),
            'notes' => (string) $this->input('notes', ''),
        ]);
        if ((int) ($result['status'] ?? 0) === 200) {
            SessionManager::flash('success', __('hr_ess_certificate_requested'));
        } else {
            SessionManager::flash('error', (string) ($result['body']['message'] ?? __('invalid_request')));
        }
        $this->redirect(rateb_url(rateb_app_route('hr/ess')));
    }
}

/** Phase P — Manager My Team portal (Admin shell). */
final class HrManagerPortalController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $userId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $svc = new \Rateb\App\Services\HrManagerTeamService();
        $team = $companyId > 0 && $userId > 0 ? $svc->myTeam($userId, $companyId) : ['status' => 401, 'body' => []];
        $approvals = $companyId > 0 && $userId > 0 ? $svc->teamApprovals($userId, $companyId) : ['status' => 401, 'body' => ['items' => []]];
        $leave = $companyId > 0 && $userId > 0 ? $svc->teamLeave($userId, $companyId, 'pending') : ['status' => 401, 'body' => ['items' => []]];
        $saudi = (new \Rateb\App\Services\HrSaudiComplianceFoundationService())->foundationAudit($companyId);
        $this->view('company/hr/manager/index', [
            'title' => __('hr_manager_my_team'),
            'companyId' => $companyId,
            'team' => (int) ($team['status'] ?? 0) === 200 ? ($team['body'] ?? []) : null,
            'approvals' => (int) ($approvals['status'] ?? 0) === 200 ? ($approvals['body']['items'] ?? []) : [],
            'pendingLeave' => (int) ($leave['status'] ?? 0) === 200 ? ($leave['body']['items'] ?? []) : [],
            'error' => (int) ($team['status'] ?? 0) === 200 ? null : ($team['body']['message'] ?? __('employee_unbound')),
            'saudiFoundation' => $saudi,
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/manager'),
        ], 'main');
    }

    public function decide(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $userId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route('hr/manager')));
            return;
        }
        $result = (new \Rateb\App\Services\HrManagerTeamService())->decide(
            $userId,
            $companyId,
            (string) $this->input('source_key', ''),
            (int) $this->input('record_id', 0),
            (string) $this->input('action', 'approve'),
            (string) $this->input('comment', '')
        );
        if ((int) ($result['status'] ?? 0) === 200) {
            SessionManager::flash('success', (string) ($result['body']['decision']['message'] ?? __('hr_inbox_approved')));
        } else {
            SessionManager::flash('error', (string) ($result['body']['message'] ?? __('access_denied')));
        }
        $this->redirect(rateb_url(rateb_app_route('hr/manager')));
    }
}
