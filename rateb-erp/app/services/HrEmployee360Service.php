<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\AuditLog;
use Rateb\App\Models\Document;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrDocument;
use Rateb\App\Models\HrEmployeeRequest;
use Rateb\App\Models\HrPayrollStructure;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\PayrollLine;
use PDO;

/**
 * Phase I — Employee Master 360 (read-only aggregation).
 *
 * Canonical employee: rateb_employees.id + company_id.
 * Does not mutate domain state. Does not replace Employee / HrService SoT.
 */
final class HrEmployee360Service
{
    public const TAB_OVERVIEW = 'overview';
    public const TAB_EMPLOYMENT = 'employment';
    public const TAB_SALARY = 'salary';
    public const TAB_ATTENDANCE = 'attendance';
    public const TAB_LEAVES = 'leaves';
    public const TAB_REQUESTS = 'requests';
    public const TAB_LETTERS = 'letters';
    public const TAB_PAYROLL = 'payroll';
    public const TAB_DOCUMENTS = 'documents';
    public const TAB_DECISIONS = 'decisions';
    public const TAB_VIOLATIONS = 'violations';
    public const TAB_TIMELINE = 'timeline';

    /** @var list<string> */
    public const TABS = [
        self::TAB_OVERVIEW,
        self::TAB_EMPLOYMENT,
        self::TAB_SALARY,
        self::TAB_ATTENDANCE,
        self::TAB_LEAVES,
        self::TAB_REQUESTS,
        self::TAB_LETTERS,
        self::TAB_PAYROLL,
        self::TAB_DOCUMENTS,
        self::TAB_DECISIONS,
        self::TAB_VIOLATIONS,
        self::TAB_TIMELINE,
    ];

    private const LETTER_TYPES = [
        'salary_certificate',
        'employment_certificate',
        'experience_letter',
        'end_of_service',
    ];

    private HrService $hr;
    private FormLookupService $lookup;
    private HrApprovalMatrixService $matrix;

    public function __construct(
        ?HrService $hr = null,
        ?FormLookupService $lookup = null,
        ?HrApprovalMatrixService $matrix = null
    ) {
        $this->hr = $hr ?? new HrService();
        $this->lookup = $lookup ?? new FormLookupService();
        $this->matrix = $matrix ?? new HrApprovalMatrixService();
    }

    /**
     * Load employee only when company matches. Foreign tenant → null (404, no existence leak).
     *
     * @return array<string, mixed>|null
     */
    public function findEmployeeForCompany(int $companyId, int $employeeId): ?array
    {
        if ($companyId < 1 || $employeeId < 1) {
            return null;
        }

        return (new Employee())->queryOne(
            'SELECT id, company_id, employee_code, name, email, phone, national_id,
                    department_id, job_title_id, branch_id, job_title, hire_date,
                    salary_base, user_id, status, notes, created_at, updated_at
             FROM rateb_employees
             WHERE id = :id AND company_id = :cid
             LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
    }

    /**
     * Shell: header + KPIs + overview (initial paint). Secondary tabs load via loadTab().
     *
     * @param array{
     *   can_manage_employees?:bool,
     *   can_view_salary?:bool,
     *   can_view_payroll?:bool,
     *   can_view_leaves?:bool,
     *   can_view_attendance?:bool,
     *   can_create_leave?:bool,
     *   can_create_request?:bool
     * } $auth
     * @return array<string, mixed>|null
     */
    public function loadShell(int $companyId, int $employeeId, array $auth = []): ?array
    {
        $emp = $this->findEmployeeForCompany($companyId, $employeeId);
        if ($emp === null) {
            return null;
        }

        $canViewSalary = (bool) ($auth['can_view_salary'] ?? false);
        $year = (int) date('Y');
        $balances = $this->hr->leaveBalancesForEmployee($employeeId, $year);
        $attendanceYtd = $this->hr->employeeAttendanceYtd($employeeId) ?? [
            'present' => 0,
            'absent' => 0,
            'on_leave' => 0,
        ];
        $pendingLeaves = $this->countByStatus($companyId, $employeeId, 'rateb_leave_requests', 'pending');
        $pendingRequests = $this->countByStatus($companyId, $employeeId, 'rateb_hr_employee_requests', 'pending');

        $leaveKpi = $this->summarizeBalances($balances);

        $header = $this->buildHeader($emp, $companyId);
        $overview = $this->buildOverview($emp, $header, $leaveKpi, $attendanceYtd, $pendingLeaves, $pendingRequests, $canViewSalary);

        return [
            'employee' => $this->sanitizeEmployee($emp, $canViewSalary),
            'header' => $header,
            'overview' => $overview,
            'kpis' => [
                'leave_remaining' => $leaveKpi['remaining'],
                'leave_used' => $leaveKpi['used'],
                'leave_entitled' => $leaveKpi['entitled'],
                'pending_leaves' => $pendingLeaves,
                'pending_requests' => $pendingRequests,
                'attendance_ytd' => $attendanceYtd,
                'salary_base' => $canViewSalary ? (float) ($emp['salary_base'] ?? 0) : null,
            ],
            'actions' => [
                'edit' => (bool) ($auth['can_manage_employees'] ?? false),
                'new_leave' => (bool) ($auth['can_create_leave'] ?? false),
                'new_request' => (bool) ($auth['can_create_request'] ?? false),
                'view_attendance' => (bool) ($auth['can_view_attendance'] ?? true),
                'view_payroll' => (bool) ($auth['can_view_payroll'] ?? false),
                'view_documents' => true,
                'view_requests' => (bool) ($auth['can_view_leaves'] ?? true),
            ],
            'tabs' => self::TABS,
            'ess_linked' => (int) ($emp['user_id'] ?? 0) > 0,
            'deferred' => [
                'unified_documents' => true,
            ],
            'canonical_source' => 'rateb_employees',
        ];
    }

    /**
     * @param array{can_view_salary?:bool,can_view_payroll?:bool} $auth
     * @return array<string, mixed>|null
     */
    public function loadTab(int $companyId, int $employeeId, string $tab, array $auth = []): ?array
    {
        $emp = $this->findEmployeeForCompany($companyId, $employeeId);
        if ($emp === null) {
            return null;
        }
        $tab = strtolower(trim($tab));
        if (!in_array($tab, self::TABS, true)) {
            return null;
        }

        $canViewSalary = (bool) ($auth['can_view_salary'] ?? false);
        $canViewPayroll = (bool) ($auth['can_view_payroll'] ?? false);

        $header = $this->buildHeader($emp, $companyId);

        return match ($tab) {
            self::TAB_OVERVIEW => [
                'tab' => $tab,
                'data' => $this->buildOverview(
                    $emp,
                    $header,
                    $this->summarizeBalances($this->hr->leaveBalancesForEmployee($employeeId, (int) date('Y'))),
                    $this->hr->employeeAttendanceYtd($employeeId) ?? ['present' => 0, 'absent' => 0, 'on_leave' => 0],
                    $this->countByStatus($companyId, $employeeId, 'rateb_leave_requests', 'pending'),
                    $this->countByStatus($companyId, $employeeId, 'rateb_hr_employee_requests', 'pending'),
                    $canViewSalary
                ),
            ],
            self::TAB_EMPLOYMENT => [
                'tab' => $tab,
                'data' => $this->tabEmployment($emp, $companyId),
            ],
            self::TAB_SALARY => [
                'tab' => $tab,
                'data' => $this->tabSalary($emp, $companyId, $canViewSalary),
            ],
            self::TAB_ATTENDANCE => [
                'tab' => $tab,
                'data' => $this->tabAttendance($companyId, $employeeId),
            ],
            self::TAB_LEAVES => [
                'tab' => $tab,
                'data' => $this->tabLeaves($companyId, $employeeId),
            ],
            self::TAB_REQUESTS => [
                'tab' => $tab,
                'data' => $this->tabRequests($companyId, $employeeId, false),
            ],
            self::TAB_LETTERS => [
                'tab' => $tab,
                'data' => $this->tabRequests($companyId, $employeeId, true),
            ],
            self::TAB_PAYROLL => [
                'tab' => $tab,
                'data' => $this->tabPayroll($companyId, $employeeId, $canViewPayroll),
            ],
            self::TAB_DOCUMENTS => [
                'tab' => $tab,
                'data' => $this->tabDocuments($companyId, $employeeId),
            ],
            self::TAB_DECISIONS => [
                'tab' => $tab,
                'data' => $this->tabDecisions($companyId, $employeeId),
            ],
            self::TAB_VIOLATIONS => [
                'tab' => $tab,
                'data' => $this->tabViolations($companyId, $employeeId),
            ],
            self::TAB_TIMELINE => [
                'tab' => $tab,
                'data' => $this->tabTimeline($companyId, $employeeId, $emp),
            ],
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private function buildHeader(array $emp, int $companyId): array
    {
        $deptId = (int) ($emp['department_id'] ?? 0);
        $jobTitleId = (int) ($emp['job_title_id'] ?? 0);
        $branchId = (int) ($emp['branch_id'] ?? 0);
        $jobTitle = trim((string) ($emp['job_title'] ?? ''));
        if ($jobTitle === '' && $jobTitleId > 0) {
            $jobTitle = $this->lookup->resolveFkLabel('hr_job_titles', $jobTitleId);
        }

        return [
            'id' => (int) ($emp['id'] ?? 0),
            'name' => (string) ($emp['name'] ?? ''),
            'employee_code' => (string) ($emp['employee_code'] ?? ''),
            'status' => (string) ($emp['status'] ?? 'active'),
            'department' => $deptId > 0 ? $this->lookup->resolveFkLabel('hr_departments', $deptId) : '',
            'position' => $jobTitle,
            'branch' => $branchId > 0 ? $this->lookup->resolveFkLabel('branches', $branchId) : '',
            'hire_date' => (string) ($emp['hire_date'] ?? ''),
            'initials' => $this->initials((string) ($emp['name'] ?? '')),
            'manager' => $this->resolveManagerName($companyId, (int) ($emp['id'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $emp
     * @param array<string, mixed> $header
     * @param array{entitled:float,used:float,remaining:float} $leaveKpi
     * @param array{present:int,absent:int,on_leave:int} $attendanceYtd
     * @return array<string, mixed>
     */
    private function buildOverview(
        array $emp,
        array $header,
        array $leaveKpi,
        array $attendanceYtd,
        int $pendingLeaves,
        int $pendingRequests,
        bool $canViewSalary
    ): array {
        return [
            'personal' => [
                'name' => (string) ($emp['name'] ?? ''),
                'email' => (string) ($emp['email'] ?? ''),
                'phone' => (string) ($emp['phone'] ?? ''),
                'national_id' => (string) ($emp['national_id'] ?? ''),
                'employee_code' => (string) ($emp['employee_code'] ?? ''),
            ],
            'employment' => [
                'department' => $header['department'],
                'position' => $header['position'],
                'branch' => $header['branch'],
                'manager' => $header['manager'],
                'hire_date' => $header['hire_date'],
                'status' => $header['status'],
                'contracts_available' => false,
            ],
            'kpis' => [
                'leave_remaining' => $leaveKpi['remaining'],
                'leave_used' => $leaveKpi['used'],
                'leave_entitled' => $leaveKpi['entitled'],
                'pending_leaves' => $pendingLeaves,
                'pending_requests' => $pendingRequests,
                'attendance_present_ytd' => (int) ($attendanceYtd['present'] ?? 0),
                'attendance_absent_ytd' => (int) ($attendanceYtd['absent'] ?? 0),
                'attendance_leave_ytd' => (int) ($attendanceYtd['on_leave'] ?? 0),
                'salary_base' => $canViewSalary ? (float) ($emp['salary_base'] ?? 0) : null,
            ],
            'ess_linked' => (int) ($emp['user_id'] ?? 0) > 0,
            'notes' => (string) ($emp['notes'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private function tabEmployment(array $emp, int $companyId): array
    {
        $header = $this->buildHeader($emp, $companyId);
        $employeeId = (int) ($emp['id'] ?? 0);
        $contracts = [];
        try {
            $contracts = (new HrEmploymentContractService())->listForEmployee($companyId, $employeeId);
        } catch (\Throwable $e) {
            $contracts = [];
        }

        return [
            'fields' => [
                'employee_code' => (string) ($emp['employee_code'] ?? ''),
                'department' => $header['department'],
                'position' => $header['position'],
                'branch' => $header['branch'],
                'manager' => $header['manager'],
                'hire_date' => $header['hire_date'],
                'status' => $header['status'],
                'national_id' => (string) ($emp['national_id'] ?? ''),
            ],
            'contracts' => $contracts,
            'contracts_deferred' => false,
            'contracts_register_url' => rateb_url(rateb_app_route('hr/employment-contracts')),
        ];
    }

    /**
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private function tabSalary(array $emp, int $companyId, bool $canViewSalary): array
    {
        if (!$canViewSalary) {
            return [
                'authorized' => false,
                'basic_salary' => null,
                'components' => [],
            ];
        }

        $employeeId = (int) ($emp['id'] ?? 0);
        $rows = (new HrPayrollStructure())->query(
            "SELECT ps.value, pc.code, pc.name, pc.component_type, pc.calc_type
             FROM rateb_hr_payroll_structures ps
             JOIN rateb_hr_payroll_components pc ON pc.id = ps.component_id AND pc.company_id = ps.company_id
             WHERE ps.company_id = :cid AND ps.employee_id = :eid AND pc.status = 'active'
             ORDER BY pc.component_type ASC, pc.name ASC
             LIMIT 50",
            ['cid' => $companyId, 'eid' => $employeeId]
        );

        return [
            'authorized' => true,
            'basic_salary' => (float) ($emp['salary_base'] ?? 0),
            'components' => is_array($rows) ? $rows : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabAttendance(int $companyId, int $employeeId): array
    {
        $ytd = $this->hr->employeeAttendanceYtd($employeeId) ?? [
            'present' => 0,
            'absent' => 0,
            'on_leave' => 0,
        ];
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $month = (new AttendanceRecord())->queryOne(
            "SELECT SUM(CASE WHEN status IN ('present') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS on_leave,
                    SUM(CASE WHEN status = 'holiday' THEN 1 ELSE 0 END) AS holiday
             FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid
               AND attendance_date BETWEEN :s AND :e",
            ['cid' => $companyId, 'eid' => $employeeId, 's' => $monthStart, 'e' => $monthEnd]
        );

        return [
            'month' => [
                'year' => (int) date('Y'),
                'month' => (int) date('n'),
                'present' => (int) ($month['present'] ?? 0),
                'late' => (int) ($month['late'] ?? 0),
                'absent' => (int) ($month['absent'] ?? 0),
                'on_leave' => (int) ($month['on_leave'] ?? 0),
                'holiday' => (int) ($month['holiday'] ?? 0),
            ],
            'ytd' => $ytd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabLeaves(int $companyId, int $employeeId): array
    {
        $year = (int) date('Y');
        $balances = $this->hr->leaveBalancesForEmployee($employeeId, $year);
        $recent = $this->hr->listLeaveRequestsForEmployee($companyId, $employeeId);
        $today = date('Y-m-d');
        $upcoming = [];
        foreach ($recent as $row) {
            if ((string) ($row['status'] ?? '') !== 'approved') {
                continue;
            }
            if ((string) ($row['end_date'] ?? '') < $today) {
                continue;
            }
            $upcoming[] = $row;
            if (count($upcoming) >= 10) {
                break;
            }
        }
        $pending = 0;
        foreach ($recent as $row) {
            if ((string) ($row['status'] ?? '') === 'pending') {
                $pending++;
            }
        }

        return [
            'balances' => $balances,
            'summary' => $this->summarizeBalances($balances),
            'pending_count' => $pending,
            'recent' => array_slice($recent, 0, 20),
            'upcoming' => $upcoming,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabRequests(int $companyId, int $employeeId, bool $lettersOnly): array
    {
        $select = 'id, request_no, request_type, request_date, status, processed_at, notes, created_at';
        try {
            if (Database::liveTableHasColumn('rateb_hr_employee_requests', 'document_id')) {
                $select .= ', document_id, issued_at';
            }
        } catch (\Throwable $e) {
            // pre-migration
        }
        $rows = (new HrEmployeeRequest())->query(
            "SELECT {$select}
             FROM rateb_hr_employee_requests
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT 50",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $type = (string) ($row['request_type'] ?? '');
            $isLetter = in_array($type, self::LETTER_TYPES, true)
                || HrLetterIssueService::isLetterType($type);
            if ($lettersOnly && !$isLetter) {
                continue;
            }
            if (!$lettersOnly && $isLetter) {
                // Letters have their own tab; keep general requests cleaner.
                continue;
            }
            $progress = null;
            if ((string) ($row['status'] ?? '') === 'pending') {
                $progress = $this->matrix->progressSummary('hr_request', (int) ($row['id'] ?? 0), $companyId);
            }
            $docId = (int) ($row['document_id'] ?? 0);
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'request_no' => (string) ($row['request_no'] ?? ''),
                'request_type' => $type,
                'request_date' => (string) ($row['request_date'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'processed_at' => (string) ($row['processed_at'] ?? ''),
                'issued_at' => (string) ($row['issued_at'] ?? ''),
                'stage_name' => $progress['stage_name'] ?? null,
                'stage_order' => $progress['current_stage_order'] ?? null,
                'max_stage_order' => $progress['max_stage_order'] ?? null,
                'pdf_available' => $docId > 0,
                'download_url' => $docId > 0
                    ? rateb_url(rateb_app_route('hr/letters/' . (int) ($row['id'] ?? 0) . '/download'))
                    : null,
                'issue_url' => ((string) ($row['status'] ?? '') === 'approved')
                    ? rateb_url(rateb_app_route('hr/letters/' . (int) ($row['id'] ?? 0) . '/issue'))
                    : null,
            ];
        }

        return [
            'items' => $out,
            'pdf_deferred' => false,
            'letters_url' => rateb_url(rateb_app_route('hr/letters')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabPayroll(int $companyId, int $employeeId, bool $canViewPayroll): array
    {
        if (!$canViewPayroll) {
            return ['authorized' => false, 'items' => []];
        }

        $rows = (new PayrollLine())->query(
            "SELECT pl.id, pl.basic_salary, pl.allowances, pl.deductions, pl.net_salary, pl.notes,
                    pp.id AS period_id, pp.period_year, pp.period_month, pp.status AS period_status
             FROM rateb_payroll_lines pl
             JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
             WHERE pl.company_id = :cid AND pl.employee_id = :eid
             ORDER BY pp.period_year DESC, pp.period_month DESC, pl.id DESC
             LIMIT 24",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $basic = (float) ($row['basic_salary'] ?? 0);
            $allow = (float) ($row['allowances'] ?? 0);
            $items[] = [
                'period_id' => (int) ($row['period_id'] ?? 0),
                'period_year' => (int) ($row['period_year'] ?? 0),
                'period_month' => (int) ($row['period_month'] ?? 0),
                'gross' => round($basic + $allow, 2),
                'basic_salary' => $basic,
                'allowances' => $allow,
                'deductions' => (float) ($row['deductions'] ?? 0),
                'net' => (float) ($row['net_salary'] ?? 0),
                'status' => (string) ($row['period_status'] ?? ''),
                // Phase D: posted = period lock only (not GL / not bank).
            ];
        }

        return [
            'authorized' => true,
            'items' => $items,
            'posted_means_period_lock' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabDocuments(int $companyId, int $employeeId): array
    {
        $files = (new Document())->query(
            "SELECT id, title, file_name, mime_type, entity_type, created_at
             FROM rateb_documents
             WHERE company_id = :cid AND entity_id = :eid
               AND entity_type IN ('hr_employees','employees','employee')
             ORDER BY id DESC
             LIMIT 50",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $meta = (new HrDocument())->query(
            'SELECT id, title, doc_type, issue_date, expiry_date, created_at
             FROM rateb_hr_documents
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT 50',
            ['cid' => $companyId, 'eid' => $employeeId]
        );

        return [
            'files' => is_array($files) ? $files : [],
            'metadata' => is_array($meta) ? $meta : [],
            'unified_center_deferred' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabDecisions(int $companyId, int $employeeId): array
    {
        $svc = new HrDecisionService();
        if (!$svc->schemaReady()) {
            return [
                'items' => [],
                'available' => false,
                'decisions_url' => rateb_url(rateb_app_route('hr/decisions')),
                'note' => 'decisions_module_deferred',
            ];
        }
        $items = $svc->listForEmployee($companyId, $employeeId, 30);

        return [
            'items' => $items,
            'available' => true,
            'decisions_url' => rateb_url(rateb_app_route('hr/decisions')),
            'note' => $items === [] ? 'no_records' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tabViolations(int $companyId, int $employeeId): array
    {
        if (!$this->tableExists('rateb_hrm_disciplinary_actions')
            || !$this->tableExists('rateb_hrm_employee_profiles')
        ) {
            return [
                'items' => [],
                'available' => false,
                'disciplinary_url' => rateb_url(rateb_app_route('hr/disciplinary')),
                'note' => 'violations_module_deferred',
            ];
        }

        try {
            $items = (new HrDisciplinaryService())->listForEmployee($companyId, $employeeId, 20);
        } catch (\Throwable $e) {
            return [
                'items' => [],
                'available' => false,
                'disciplinary_url' => rateb_url(rateb_app_route('hr/disciplinary')),
                'note' => 'violations_module_deferred',
            ];
        }

        return [
            'items' => $items,
            'available' => true,
            'disciplinary_url' => rateb_url(rateb_app_route('hr/disciplinary/create')) . '?employee_id=' . $employeeId,
            'note' => $items === [] ? 'no_records' : null,
        ];
    }

    /**
     * Read-only timeline from existing domain rows + audit logs (no new event store).
     *
     * @param array<string, mixed> $emp
     * @return array{items: list<array<string, mixed>>}
     */
    private function tabTimeline(int $companyId, int $employeeId, array $emp): array
    {
        $events = [];

        $createdAt = (string) ($emp['created_at'] ?? '');
        if ($createdAt !== '') {
            $events[] = [
                'at' => $createdAt,
                'type' => 'employee_created',
                'label' => 'employee_created',
                'detail' => (string) ($emp['employee_code'] ?? ''),
            ];
        }

        $audits = (new AuditLog())->query(
            "SELECT action, entity_type, created_at, payload
             FROM rateb_audit_logs
             WHERE company_id = :cid
               AND entity_id = :eid
               AND entity_type IN ('hr_employees','hr_leave','hr_payroll_period')
               AND action IN ('salary_changed','salary_created','create','update','leave_approved','leave_rejected','leave_created','leave_submitted','leave_cancelled')
             ORDER BY id DESC
             LIMIT 40",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        foreach (is_array($audits) ? $audits : [] as $row) {
            $events[] = [
                'at' => (string) ($row['created_at'] ?? ''),
                'type' => (string) ($row['action'] ?? 'audit'),
                'label' => (string) ($row['action'] ?? 'audit'),
                'detail' => (string) ($row['entity_type'] ?? ''),
            ];
        }

        $leaves = (new LeaveRequest())->query(
            'SELECT id, status, start_date, end_date, days, created_at, approved_at
             FROM rateb_leave_requests
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT 30',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        foreach (is_array($leaves) ? $leaves : [] as $row) {
            $status = (string) ($row['status'] ?? '');
            $at = (string) (($row['approved_at'] ?? '') !== '' && $row['approved_at'] !== null
                ? $row['approved_at']
                : ($row['created_at'] ?? ''));
            $events[] = [
                'at' => $at,
                'type' => 'leave_' . $status,
                'label' => 'leave_' . $status,
                'detail' => trim(($row['start_date'] ?? '') . ' → ' . ($row['end_date'] ?? '') . ' (' . ($row['days'] ?? '') . ')'),
            ];
        }

        $reqs = (new HrEmployeeRequest())->query(
            'SELECT request_type, status, created_at, request_date
             FROM rateb_hr_employee_requests
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT 20',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        foreach (is_array($reqs) ? $reqs : [] as $row) {
            $events[] = [
                'at' => (string) ($row['created_at'] ?? $row['request_date'] ?? ''),
                'type' => 'request_' . (string) ($row['status'] ?? ''),
                'label' => 'request_submitted',
                'detail' => (string) ($row['request_type'] ?? ''),
            ];
        }

        if ((new HrDecisionService())->schemaReady()) {
            try {
                $decisions = Database::connection()->prepare(
                    'SELECT decision_no, decision_type, status, created_at, executed_at, effective_date
                     FROM rateb_hr_decisions
                     WHERE company_id = :cid AND employee_id = :eid
                     ORDER BY id DESC
                     LIMIT 20'
                );
                $decisions->execute(['cid' => $companyId, 'eid' => $employeeId]);
                foreach ($decisions->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $at = (string) (($row['executed_at'] ?? '') !== '' && $row['executed_at'] !== null
                        ? $row['executed_at']
                        : ($row['created_at'] ?? $row['effective_date'] ?? ''));
                    $events[] = [
                        'at' => $at,
                        'type' => 'decision_' . (string) ($row['status'] ?? ''),
                        'label' => 'hr_decision',
                        'detail' => trim(
                            (string) ($row['decision_no'] ?? '') . ' · '
                            . (string) ($row['decision_type'] ?? '')
                        ),
                    ];
                }
            } catch (\Throwable $e) {
                // schema drift — skip
            }
        }

        $payroll = (new PayrollLine())->query(
            "SELECT pp.period_year, pp.period_month, pp.status, pp.created_at, pl.net_salary
             FROM rateb_payroll_lines pl
             JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
             WHERE pl.company_id = :cid AND pl.employee_id = :eid
             ORDER BY pp.period_year DESC, pp.period_month DESC
             LIMIT 12",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        foreach (is_array($payroll) ? $payroll : [] as $row) {
            $events[] = [
                'at' => (string) ($row['created_at'] ?? ''),
                'type' => 'payroll_' . (string) ($row['status'] ?? ''),
                'label' => 'payroll_period',
                'detail' => sprintf(
                    '%04d-%02d · %s',
                    (int) ($row['period_year'] ?? 0),
                    (int) ($row['period_month'] ?? 0),
                    (string) ($row['status'] ?? '')
                ),
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return ['items' => array_slice($events, 0, 60)];
    }

    /**
     * @param list<array<string, mixed>> $balances
     * @return array{entitled:float,used:float,remaining:float}
     */
    private function summarizeBalances(array $balances): array
    {
        $entitled = 0.0;
        $used = 0.0;
        foreach ($balances as $bal) {
            $entitled += (float) ($bal['entitled_days'] ?? 0);
            $used += (float) ($bal['used_days'] ?? 0);
        }

        return [
            'entitled' => round($entitled, 1),
            'used' => round($used, 1),
            'remaining' => round($entitled - $used, 1),
        ];
    }

    private function countByStatus(int $companyId, int $employeeId, string $table, string $status): int
    {
        $allowed = [
            'rateb_leave_requests' => true,
            'rateb_hr_employee_requests' => true,
        ];
        if (!isset($allowed[$table])) {
            return 0;
        }
        $row = (new Employee())->queryOne(
            "SELECT COUNT(*) AS c FROM {$table}
             WHERE company_id = :cid AND employee_id = :eid AND status = :st",
            ['cid' => $companyId, 'eid' => $employeeId, 'st' => $status]
        );

        return (int) ($row['c'] ?? 0);
    }

    private function resolveManagerName(int $companyId, int $employeeId): string
    {
        if ($companyId < 1 || $employeeId < 1) {
            return '';
        }
        if (!$this->tableExists('rateb_hrm_employee_profiles')) {
            return '';
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT m.first_name, m.last_name, m.code
                 FROM rateb_hrm_employee_profiles p
                 LEFT JOIN rateb_hrm_employee_profiles m ON m.id = p.manager_profile_id AND m.company_id = p.company_id
                 WHERE p.company_id = :cid AND p.legacy_employee_id = :eid
                 LIMIT 1'
            );
            $stmt->execute(['cid' => $companyId, 'eid' => $employeeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return '';
            }
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }

            return (string) ($row['code'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private function sanitizeEmployee(array $emp, bool $canViewSalary): array
    {
        $out = $emp;
        if (!$canViewSalary) {
            unset($out['salary_base']);
        }
        // Never expose another tenant's user_id beyond boolean ESS link in shell.
        return $out;
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_substr((string) ($parts[0] ?? $name), 0, 1);
        $second = count($parts) > 1 ? mb_substr((string) $parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first . $second);
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $cache[$table] = Database::tableExists($table);
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}
