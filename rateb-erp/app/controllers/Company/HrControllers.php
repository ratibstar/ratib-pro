<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\ApprovalOversightService;
use Rateb\App\Services\DocumentCodeService;
use Rateb\App\Services\HrApprovalInboxService;
use Rateb\App\Services\HrEmployee360Service;
use Rateb\App\Services\HrEmployeeIntegrityService;
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
        $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $cc = (new \Rateb\App\Services\HrCommandCenterService())->dashboard($companyId, $actorUserId);
        $this->view('company/hr/dashboard', [
            'title' => __('hr_command_center'),
            'companyId' => $companyId,
            'stats' => $cc['stats'],
            'inboxCounts' => $cc['inbox'],
            'approvalCenter' => $cc['approval_center'],
            'pendingDecisions' => $cc['pending_decisions'],
            'contractsExpiring' => $cc['contracts_expiring'],
            'contractsExpiringCount' => $cc['contracts_expiring_count'],
            'recentPayrolls' => $cc['recent_payrolls'],
            'recentRequests' => $cc['recent_requests'],
            'recentDecisions' => $cc['recent_decisions'],
            'upcomingLeaves' => $cc['upcoming_leaves'],
            'alerts' => $cc['alerts'],
            'quickActions' => $cc['quick_actions'],
            'hubLinks' => $cc['hub_links'],
            'analyticsWidgets' => $cc['analytics_widgets'] ?? [],
            'saudiReadiness' => $cc['saudi_readiness'] ?? [],
            'ops' => $cc['ops'] ?? [],
            'overdueApprovals' => (int) ($cc['overdue_approvals'] ?? 0),
            'contractMilestones' => $cc['contract_milestones'] ?? ['d30' => 0, 'd15' => 0, 'd7' => 0],
            'attendanceAlerts' => $cc['attendance_alerts'] ?? ['absent' => 0, 'late' => 0, 'date' => date('Y-m-d')],
            'hrTasks' => $cc['hr_tasks'] ?? [],
            'lookupUrl' => rateb_url(rateb_app_route('hr/employees/lookup')),
        ], 'main');
    }
}

/**
 * Phase F + J — company-scoped HR Approval Inbox.
 * Decide uses ApprovalOversightService::process (matrix intact).
 * Legacy company hr/{id}/approve routes stay blocked.
 */
final class HrApprovalInboxController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $type = trim((string) $this->input('type', 'all'));
        if ($type === '') {
            $type = 'all';
        }
        $allowed = ['all', 'leave', 'permission', 'request', 'payroll', 'decision'];
        if (!in_array($type, $allowed, true)) {
            $type = 'all';
        }
        $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $payload = $companyId > 0
            ? (new HrApprovalInboxService())->inbox(
                $companyId,
                $type === 'all' ? null : $type,
                200,
                $actorUserId
            )
            : ['items' => [], 'counts' => ['total' => 0], 'deferred' => []];
        $this->view('company/hr/approvals/inbox', [
            'title' => __('hr_approval_inbox'),
            'companyId' => $companyId,
            'items' => $payload['items'],
            'counts' => $payload['counts'],
            'deferred' => $payload['deferred'],
            'typeFilter' => $type,
            'isSuperAdmin' => function_exists('rateb_is_super_admin') && rateb_is_super_admin(),
            'routePrefix' => rateb_app_route('hr/approvals-inbox'),
            'decideUrl' => rateb_url(rateb_app_route('hr/approvals-inbox/decide')),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    /**
     * Phase J — approve/reject from company inbox (Oversight + Matrix only).
     */
    public function decide(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/approvals-inbox')));
        }

        $companyId = rateb_resolve_ops_company_id();
        $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        $sourceKey = trim((string) $this->input('source_key', ''));
        $recordId = (int) $this->input('record_id', 0);
        $action = trim((string) $this->input('action', ''));
        $comment = trim((string) $this->input('comment', ''));
        $typeFilter = trim((string) $this->input('type_filter', 'all'));

        $redirect = rateb_url(rateb_app_route('hr/approvals-inbox'));
        if ($typeFilter !== '' && $typeFilter !== 'all') {
            $redirect .= '?type=' . rawurlencode($typeFilter);
        }

        if ($companyId < 1 || $actorUserId < 1 || $recordId < 1 || $sourceKey === '') {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect($redirect);
        }

        try {
            $result = (new HrApprovalInboxService())->decide(
                $companyId,
                $actorUserId,
                $sourceKey,
                $recordId,
                $action,
                $comment !== '' ? $comment : null
            );
            SessionManager::flash('success', (string) ($result['message'] ?? __('saved_ok')));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect($redirect);
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
        $this->tenantForeignKeys = ['department_id', 'branch_id', 'job_title_id'];
        $this->indexFields = [
            ['name' => 'employee_code', 'label' => 'employee_code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'department_id', 'label' => 'department', 'type' => 'fk', 'lookup' => 'hr_departments'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches'],
            ['name' => 'job_title_id', 'label' => 'job_title', 'type' => 'fk', 'lookup' => 'hr_job_titles'],
            ['name' => 'salary_base', 'label' => 'salary_base'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'phone', 'type' => 'text'],
            ['name' => 'national_id', 'label' => 'national_id', 'type' => 'text'],
            ['name' => 'department_id', 'label' => 'department', 'type' => 'fk', 'lookup' => 'hr_departments'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches'],
            ['name' => 'job_title_id', 'label' => 'job_title', 'type' => 'fk', 'lookup' => 'hr_job_titles'],
            ['name' => 'hire_date', 'label' => 'hire_date', 'type' => 'date'],
            ['name' => 'salary_base', 'label' => 'salary_base', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'attrs' => ['class' => 'form-control rateb-form-control rateb-ltr-num']],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'employee_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (empty($data['branch_id']) && function_exists('rateb_allowed_branch_ids')) {
            $ids = rateb_allowed_branch_ids();
            if (count($ids) === 1) {
                $data['branch_id'] = $ids[0];
            }
        }
        $jobTitleId = (int) ($data['job_title_id'] ?? 0);
        if ($jobTitleId > 0) {
            $title = (new \Rateb\App\Models\HrJobTitle())->find($jobTitleId);
            if ($title) {
                $data['job_title'] = (string) ($title['name'] ?? '');
            }
        } elseif (empty($data['job_title_id'])) {
            $data['job_title_id'] = null;
        }
        $this->assignDocumentCode($data, DocumentCodeService::PREFIX_EMPLOYEE, 'employee_code');
        $this->autoLinkEmployeeUser($data);
        return $data;
    }

    /** Link ERP user when employee email matches (ESS mobile login). */
    private function autoLinkEmployeeUser(array &$data): void
    {
        if ((int) ($data['user_id'] ?? 0) > 0) {
            return;
        }
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            return;
        }
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = (int) rateb_resolve_ops_company_id();
        }
        // Phase B: company-scoped bind only — never link a user from another tenant.
        if ($companyId < 1) {
            return;
        }
        $user = (new \Rateb\App\Models\User())->queryOne(
            'SELECT id FROM rateb_users WHERE LOWER(TRIM(email)) = :em AND company_id = :cid LIMIT 1',
            ['em' => $email, 'cid' => $companyId]
        );
        if (is_array($user) && (int) ($user['id'] ?? 0) > 0) {
            $data['user_id'] = (int) $user['id'];
        }
    }

    /**
     * Phase C — dedicated salary_base create audit (old/new + effective_date).
     *
     * @param array<string, mixed> $data
     */
    protected function afterSuccessfulStore(int $id, array $data): void
    {
        (new HrEmployeeIntegrityService())->maybeAuditOpsSalaryCreated($id, $data);
    }

    /**
     * Phase C — dedicated salary_base change audit when Admin edits employee.
     *
     * @param array<string, mixed>|null $old
     * @param array<string, mixed> $data
     */
    protected function afterSuccessfulUpdate(int $id, ?array $old, array $data): void
    {
        (new HrEmployeeIntegrityService())->maybeAuditOpsSalaryChange($id, $old, $data);
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? (int) rateb_resolve_ops_company_id()
            : 0;
        if ($companyId < 1 || $id < 1) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $auth = $this->employee360AuthFlags();
        $shell = (new HrEmployee360Service())->loadShell($companyId, $id, $auth);
        if ($shell === null) {
            // Foreign / missing employee — same 404 (no existence leak).
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $activeTab = strtolower(trim((string) ($_GET['tab'] ?? 'overview')));
        if (!in_array($activeTab, HrEmployee360Service::TABS, true)) {
            $activeTab = HrEmployee360Service::TAB_OVERVIEW;
        }

        $this->view($this->viewPrefix . '/show', [
            'title' => (string) ($shell['header']['name'] ?? __('hr_employees')),
            'routePrefix' => $this->routePrefix,
            'csrf' => Csrf::token(),
            'companyId' => $companyId,
            'employeeId' => $id,
            'shell' => $shell,
            'activeTab' => $activeTab,
            'tabEndpoint' => rateb_url($this->routePrefix . '/' . $id . '/360-tab'),
            'canManage' => (bool) ($auth['can_manage_employees'] ?? false),
            'hubLinks' => (new \Rateb\App\Services\HrCommandCenterService())->employee360HubLinks(),
        ], $this->layout());
    }

    /**
     * Phase N — bounded employee lookup for Command Center search (JSON).
     */
    public function lookup(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? (int) rateb_resolve_ops_company_id()
            : 0;
        $q = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=UTF-8');
        if ($companyId < 1) {
            echo json_encode(['success' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        $items = (new \Rateb\App\Services\HrCommandCenterService())->searchEmployees($companyId, $q);
        echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Phase I — lazy tab fragment for Employee 360 (read-only, company-scoped).
     * Default: HTML. ?format=json for diagnostics.
     */
    public function show360Tab(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? (int) rateb_resolve_ops_company_id()
            : 0;
        $tab = strtolower(trim((string) ($_GET['tab'] ?? '')));
        $format = strtolower(trim((string) ($_GET['format'] ?? 'html')));
        if ($companyId < 1 || $id < 1 || $tab === '') {
            http_response_code(404);
            if ($format === 'json') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => false, 'code' => 'not_found'], JSON_UNESCAPED_UNICODE);
            } else {
                echo '';
            }
            return;
        }
        $payload = (new HrEmployee360Service())->loadTab($companyId, $id, $tab, $this->employee360AuthFlags());
        if ($payload === null) {
            http_response_code(404);
            if ($format === 'json') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => false, 'code' => 'not_found'], JSON_UNESCAPED_UNICODE);
            } else {
                echo '';
            }
            return;
        }
        if ($format === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'tab' => $tab, 'data' => $payload['data'] ?? []], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: text/html; charset=UTF-8');
        $this->view($this->viewPrefix . '/360-tab', [
            'tab' => $tab,
            'data' => $payload['data'] ?? [],
        ], null);
    }

    /** @return array<string, bool> */
    private function employee360AuthFlags(): array
    {
        $canManageEmp = function_exists('rateb_can_manage_entity')
            ? rateb_can_manage_entity('hr-employees')
            : true;
        $canViewPayroll = function_exists('rateb_can_view_entity')
            ? rateb_can_view_entity('hr-payroll')
            : true;
        $canManagePayroll = function_exists('rateb_can_manage_entity')
            ? rateb_can_manage_entity('hr-payroll')
            : false;
        $canViewLeaves = function_exists('rateb_can_view_entity')
            ? rateb_can_view_entity('hr-leaves')
            : true;
        $canManageLeaves = function_exists('rateb_can_manage_entity')
            ? rateb_can_manage_entity('hr-leaves')
            : false;
        $canViewAttendance = function_exists('rateb_can_view_entity')
            ? rateb_can_view_entity('hr-attendance')
            : true;

        return [
            'can_manage_employees' => $canManageEmp,
            // Salary detail follows payroll view OR employee manage (existing ops pattern).
            'can_view_salary' => $canViewPayroll || $canManageEmp,
            'can_view_payroll' => $canViewPayroll || $canManagePayroll,
            'can_view_leaves' => $canViewLeaves || $canManageLeaves,
            'can_view_attendance' => $canViewAttendance,
            'can_create_leave' => $canManageLeaves,
            'can_create_request' => $canManageLeaves,
        ];
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
        $titleLookups = (new \Rateb\App\Services\FormLookupService())->get('hr_job_titles');
        $titleMap = [];
        foreach ($titleLookups as $opt) {
            $titleMap[(string) $opt['value']] = (string) $opt['label'];
        }
        $exportRows = [];
        foreach ($rows as $row) {
            $jobTitle = $titleMap[(string) ($row['job_title_id'] ?? '')] ?? (string) ($row['job_title'] ?? '');
            $exportRows[] = [
                'employee_code' => $row['employee_code'] ?? '',
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'department' => $deptMap[(string) ($row['department_id'] ?? '')] ?? '',
                'job_title' => $jobTitle,
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
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, DocumentCodeService::PREFIX_HR_DEPARTMENT, 'code');
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
    }
}

final class HrJobTitlesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\HrJobTitle();
        $this->viewPrefix = 'company/hr/job-titles';
        $this->routePrefix = rateb_app_route('hr/job-titles');
        $this->entityName = 'hr_job_titles';
        $this->permissionResource = 'hr-employees';
        $this->indexFields = [
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'active_inactive_statuses', 'translate_options' => true],
        ];
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        if (trim((string) ($data['code'] ?? '')) === '') {
            $companyId = (int) (TenantContext::companyId() ?? 0);
            if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
                $companyId = rateb_resolve_ops_company_id();
            }
            if ($companyId > 0) {
                $data['code'] = (new HrService())->nextJobTitleCode($companyId);
            }
        }
        return $data;
    }

    protected function layout(): string
    {
        return 'main';
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
            ['name' => 'check_in', 'label' => 'check_in', 'type' => 'select', 'lookup' => 'hr_time_slots', 'default' => '09:00'],
            ['name' => 'check_out', 'label' => 'check_out', 'type' => 'select', 'lookup' => 'hr_time_slots', 'default' => '17:00'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'attendance_statuses', 'translate_options' => true],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
        ];
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        TenantContext::setCompanyId($companyId);
        try {
            if ((new \Rateb\App\Models\Employee())->count() < 1) {
                SessionManager::flash('error', __('hr_attendance_need_employee'));
                $this->redirect(rateb_url(rateb_app_route('hr/employees')));
            }
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
        ]), $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }

        $data = $this->collectData();
        $missing = $this->missingRequiredFields($data);
        if ($missing !== []) {
            SessionManager::flash('error', __('form_required_fields') . ': ' . implode(', ', $missing));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }

        $this->ensureTenantCompanyForWrite($data);
        try {
            \Rateb\App\Services\TenantFkValidator::validate($data, $this->tenantForeignKeys);
            $id = $this->model->create($data);
            (new AuditService())->log('create', $this->entityName, $id, $data);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data
     *  @return array<int, string>
     */
    private function missingRequiredFields(array $data): array
    {
        $missing = [];
        foreach ($this->fields as $field) {
            if (empty($field['required'])) {
                continue;
            }
            $name = (string) $field['name'];
            $val = $data[$name] ?? null;
            if ($val === null || $val === '' || $val === 0) {
                $missing[] = __((string) ($field['label'] ?? $name));
            }
        }
        return $missing;
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

    public function create(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
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
                // Calendar-day semantics intentionally preserved (Phase H2).
                $data['days'] = (int) round(($end - $start) / 86400) + 1;
            }
        }
        return $data;
    }

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $data = $this->collectData();
        $companyId = rateb_resolve_ops_company_id();
        try {
            $id = (new HrService())->createPendingLeaveRequest(
                $companyId,
                (int) ($data['employee_id'] ?? 0),
                (int) ($data['leave_type_id'] ?? 0),
                (string) ($data['start_date'] ?? ''),
                (string) ($data['end_date'] ?? ''),
                (float) ($data['days'] ?? 0),
                isset($data['reason']) ? (string) $data['reason'] : null,
                isset($data['branch_id']) ? (int) $data['branch_id'] : null,
                (int) (SessionManager::get('rateb_user_id') ?? 0)
            );
            ApprovalOversightService::notifyPendingSubmission(
                $companyId,
                'hr_leave',
                (string) __('hr_leaves'),
                $id
            );
            SessionManager::flash('success', __('saved_ok'));
            $this->redirect(rateb_url($this->routePrefix));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
    }

    public function cancel(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            (new HrService())->cancelLeave($id, (int) (SessionManager::get('rateb_user_id') ?? 0));
            SessionManager::flash('success', __('leave_cancelled'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url($this->routePrefix));
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

    public function balances(): void
    {
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $year = (int) $this->input('year', (int) date('Y'));
        $this->view('company/hr/leaves/balances', [
            'title' => __('hr_leave_balances'),
            'leaveBalances' => (new HrService())->leaveBalancesSummary($companyId, $year),
            'balanceYear' => $year,
        ], $this->layout());
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
        $limit = rateb_list_per_page();
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
        if (($data['status'] ?? '') === '') {
            $data['status'] = 'draft';
        }
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
             JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id
             WHERE pl.period_id = :pid AND pl.company_id = :cid
             ORDER BY e.name ASC",
            ['pid' => $id, 'cid' => (int) ($period['company_id'] ?? 0)]
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

    public function exportPeriod(array $params): void
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
             JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id
             WHERE pl.period_id = :pid AND pl.company_id = :cid ORDER BY e.name ASC",
            ['pid' => $id, 'cid' => (int) ($period['company_id'] ?? 0)]
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
             JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id
             WHERE pl.id = :lid AND pl.period_id = :pid AND pl.company_id = :cid LIMIT 1",
            ['lid' => $lineId, 'pid' => $periodId, 'cid' => (int) ($period['company_id'] ?? 0)]
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
        $companyId = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
        try {
            (new HrService())->approvePayroll($id, $companyId > 0 ? $companyId : null);
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
        $companyId = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
        try {
            // Service enforces approved → posted only (cannot bypass oversight approval).
            (new HrService())->postPayroll($id, $companyId > 0 ? $companyId : null);
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
            ['name' => 'status', 'label' => 'status', 'type' => 'status'],
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

    public function leaves(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $year = max(2020, (int) $this->input('year', (int) date('Y')));
        $rows = (new HrService())->leaveReport($companyId, $year);
        $this->view('company/hr/reports-leaves', [
            'title' => __('hr_leave_report'),
            'year' => $year,
            'rows' => $rows,
            'exportRoute' => rateb_app_url('hr/reports/leaves/export') . '?year=' . $year,
            'exportEnabled' => function_exists('rateb_can_export_entity') ? rateb_can_export_entity('hr') : true,
        ], 'main');
    }

    public function leavesExport(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $year = max(2020, (int) $this->input('year', (int) date('Y')));
        $rows = (new HrService())->leaveReport($companyId, $year);
        \Rateb\App\Controllers\Shared\ExportController::send('hr_leave_report', [
            ['name' => 'employee_code', 'label' => __('employee_code')],
            ['name' => 'employee_name', 'label' => __('name')],
            ['name' => 'leave_type', 'label' => __('leave_type')],
            ['name' => 'total_days', 'label' => __('days')],
            ['name' => 'approved_count', 'label' => __('approved')],
        ], $rows, __('hr_leave_report'), 'hr');
    }
}

/**
 * Phase K — HR employment contracts register (not commercial contracts).
 */
final class HrEmploymentContractsController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $status = trim((string) $this->input('status', 'all'));
        $svc = new \Rateb\App\Services\HrEmploymentContractService();
        $items = $companyId > 0
            ? $svc->listForCompany($companyId, $status === 'all' ? null : $status)
            : [];
        $employees = $companyId > 0
            ? (new \Rateb\App\Models\Employee())->query(
                "SELECT id, name, employee_code FROM rateb_employees
                 WHERE company_id = :cid AND status = 'active'
                 ORDER BY name ASC LIMIT 500",
                ['cid' => $companyId]
            )
            : [];
        $this->view('company/hr/employment-contracts/index', [
            'title' => __('hr_employment_contracts'),
            'companyId' => $companyId,
            'items' => $items,
            'employees' => is_array($employees) ? $employees : [],
            'statusFilter' => $status,
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage') || rateb_can('hr-employees.create')),
            'csrf' => Csrf::token(),
            'storeUrl' => rateb_url(rateb_app_route('hr/employment-contracts')),
            'routePrefix' => rateb_app_route('hr/employment-contracts'),
        ], 'main');
    }

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        HrService::bootstrapTenant();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $row = $companyId > 0
            ? (new \Rateb\App\Services\HrEmploymentContractService())->findForCompany($companyId, $id)
            : null;
        if ($row === null) {
            http_response_code(404);
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url(rateb_app_route('hr/employment-contracts')));
        }
        $this->view('company/hr/employment-contracts/show', [
            'title' => __('hr_employment_contract'),
            'companyId' => $companyId,
            'item' => $row,
            'canManage' => function_exists('rateb_can') && (rateb_can('hr.manage') || rateb_can('hr-employees.manage') || rateb_can('hr-employees.update')),
            'csrf' => Csrf::token(),
            'routePrefix' => rateb_app_route('hr/employment-contracts'),
        ], 'main');
    }

    public function store(): void
    {
        $this->guardWrite();
        $companyId = rateb_resolve_ops_company_id();
        $redirect = rateb_url(rateb_app_route('hr/employment-contracts'));
        try {
            $row = (new \Rateb\App\Services\HrEmploymentContractService())->create($companyId, [
                'employee_id' => (int) $this->input('employee_id', 0),
                'contract_no' => trim((string) $this->input('contract_no', '')),
                'start_date' => trim((string) $this->input('start_date', '')),
                'end_date' => trim((string) $this->input('end_date', '')),
                'salary' => (float) $this->input('salary', 0),
                'alert_days' => (int) $this->input('alert_days', 30),
                'notes' => trim((string) $this->input('notes', '')),
                'status' => 'draft',
            ]);
            SessionManager::flash('success', __('hr_employment_contract_created'));
            $this->redirect(rateb_url(rateb_app_route('hr/employment-contracts/' . (int) $row['id'])));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
            $this->redirect($redirect);
        }
    }

    public function update(array $params): void
    {
        $this->guardWrite();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $redirect = rateb_url(rateb_app_route('hr/employment-contracts/' . $id));
        try {
            (new \Rateb\App\Services\HrEmploymentContractService())->update($companyId, $id, [
                'start_date' => trim((string) $this->input('start_date', '')),
                'end_date' => trim((string) $this->input('end_date', '')),
                'salary' => (float) $this->input('salary', 0),
                'alert_days' => (int) $this->input('alert_days', 30),
                'notes' => trim((string) $this->input('notes', '')),
            ]);
            SessionManager::flash('success', __('saved_ok'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect($redirect);
    }

    public function activate(array $params): void
    {
        $this->guardWrite();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $redirect = rateb_url(rateb_app_route('hr/employment-contracts/' . $id));
        try {
            (new \Rateb\App\Services\HrEmploymentContractService())->activate($companyId, $id);
            SessionManager::flash('success', __('hr_employment_contract_activated'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect($redirect);
    }

    public function terminate(array $params): void
    {
        $this->guardWrite();
        $companyId = rateb_resolve_ops_company_id();
        $id = (int) ($params['id'] ?? 0);
        $redirect = rateb_url(rateb_app_route('hr/employment-contracts/' . $id));
        try {
            (new \Rateb\App\Services\HrEmploymentContractService())->terminate(
                $companyId,
                $id,
                trim((string) $this->input('notes', ''))
            );
            SessionManager::flash('success', __('hr_employment_contract_terminated'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage() !== '' ? $e->getMessage() : __('access_denied'));
        }
        $this->redirect($redirect);
    }

    private function guardWrite(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('hr/employment-contracts')));
        }
    }
}
