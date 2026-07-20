<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\Company;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrFleetVehicle;
use Rateb\App\Models\HrHoliday;
use Rateb\App\Models\HrLoan;
use Rateb\App\Models\HrPayrollStructure;
use Rateb\App\Models\LeaveBalance;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\LeaveType;
use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPeriod;

final class HrService
{
    /** @return array{employees:int,active:int,present_today:int,absent_today:int,pending_leaves:int,draft_payrolls:int} */
    public function dashboardStats(int $companyId): array
    {
        if ($companyId < 1) {
            return [
                'employees' => 0, 'active' => 0, 'present_today' => 0,
                'absent_today' => 0, 'pending_leaves' => 0, 'draft_payrolls' => 0,
            ];
        }
        $emp = (new Employee())->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active
             FROM rateb_employees WHERE company_id = :cid",
            ['cid' => $companyId]
        );
        $today = date('Y-m-d');
        $att = (new AttendanceRecord())->queryOne(
            "SELECT SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent
             FROM rateb_attendance_records
             WHERE company_id = :cid AND attendance_date = :d",
            ['cid' => $companyId, 'd' => $today]
        );
        $leaves = (new LeaveRequest())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_leave_requests
             WHERE company_id = :cid AND status = 'pending'",
            ['cid' => $companyId]
        );
        $payroll = (new PayrollPeriod())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_payroll_periods
             WHERE company_id = :cid AND status = 'draft'",
            ['cid' => $companyId]
        );
        return [
            'employees' => (int) ($emp['total'] ?? 0),
            'active' => (int) ($emp['active'] ?? 0),
            'present_today' => (int) ($att['present'] ?? 0),
            'absent_today' => (int) ($att['absent'] ?? 0),
            'pending_leaves' => (int) ($leaves['c'] ?? 0),
            'draft_payrolls' => (int) ($payroll['c'] ?? 0),
        ];
    }

    /** @return list<array{code:string,paid:int,days_per_year:float|null}> */
    public static function defaultLeaveTypeDefinitions(): array
    {
        return [
            ['code' => 'annual', 'paid' => 1, 'days_per_year' => 21.0],
            ['code' => 'sick', 'paid' => 1, 'days_per_year' => 30.0],
            ['code' => 'unpaid', 'paid' => 0, 'days_per_year' => null],
            ['code' => 'emergency', 'paid' => 1, 'days_per_year' => 5.0],
            ['code' => 'maternity', 'paid' => 1, 'days_per_year' => 70.0],
            ['code' => 'paternity', 'paid' => 1, 'days_per_year' => 3.0],
            ['code' => 'hajj', 'paid' => 1, 'days_per_year' => 15.0],
            ['code' => 'marriage', 'paid' => 1, 'days_per_year' => 5.0],
            ['code' => 'bereavement', 'paid' => 1, 'days_per_year' => 5.0],
            ['code' => 'study', 'paid' => 1, 'days_per_year' => null],
            ['code' => 'exam', 'paid' => 1, 'days_per_year' => null],
            ['code' => 'compensatory', 'paid' => 1, 'days_per_year' => null],
            ['code' => 'work_injury', 'paid' => 1, 'days_per_year' => null],
            ['code' => 'iddah', 'paid' => 1, 'days_per_year' => 130.0],
        ];
    }

    public function ensureDefaultLeaveTypes(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $model = new LeaveType();
        $existing = $model->query(
            'SELECT id, code, name FROM rateb_leave_types WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $byCode = [];
        foreach ($existing as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $byCode[$code] = (int) ($row['id'] ?? 0);
            }
        }
        foreach (self::defaultLeaveTypeDefinitions() as $row) {
            $code = (string) $row['code'];
            if (isset($byCode[$code])) {
                continue;
            }
            $label = function_exists('__') ? __('leave_type_' . $code) : $code;
            $model->create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $label !== 'leave_type_' . $code ? $label : $code,
                'paid' => $row['paid'],
                'days_per_year' => $row['days_per_year'],
                'status' => 'active',
            ]);
        }
    }

    /** @return list<array{code:string,key:string}> */
    public static function defaultJobTitleDefinitions(): array
    {
        return [
            ['code' => 'JT-01', 'key' => 'job_title_general_manager'],
            ['code' => 'JT-02', 'key' => 'job_title_hr_manager'],
            ['code' => 'JT-03', 'key' => 'job_title_accountant'],
            ['code' => 'JT-04', 'key' => 'job_title_procurement'],
            ['code' => 'JT-05', 'key' => 'job_title_warehouse_keeper'],
            ['code' => 'JT-06', 'key' => 'job_title_driver'],
            ['code' => 'JT-07', 'key' => 'job_title_technician'],
            ['code' => 'JT-08', 'key' => 'job_title_admin_staff'],
            ['code' => 'JT-09', 'key' => 'job_title_sales'],
        ];
    }

    public function nextJobTitleCode(int $companyId): string
    {
        if ($companyId < 1) {
            return 'JT-01';
        }
        $rows = (new \Rateb\App\Models\HrJobTitle())->query(
            'SELECT code FROM rateb_hr_job_titles WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $used = [];
        foreach ($rows as $row) {
            $used[strtoupper(trim((string) ($row['code'] ?? '')))] = true;
        }
        foreach (self::defaultJobTitleDefinitions() as $def) {
            $code = strtoupper($def['code']);
            if (!isset($used[$code])) {
                return $def['code'];
            }
        }
        for ($n = 10; $n < 100; $n++) {
            $code = 'JT-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            if (!isset($used[$code])) {
                return $code;
            }
        }
        return 'JT-99';
    }

    public function ensureDefaultJobTitles(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $model = new \Rateb\App\Models\HrJobTitle();
        $existing = $model->query(
            'SELECT code FROM rateb_hr_job_titles WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $used = [];
        foreach ($existing as $row) {
            $used[strtoupper(trim((string) ($row['code'] ?? '')))] = true;
        }
        foreach (self::defaultJobTitleDefinitions() as $row) {
            if (isset($used[strtoupper($row['code'])])) {
                continue;
            }
            $model->create([
                'company_id' => $companyId,
                'code' => $row['code'],
                'name' => function_exists('__') ? __($row['key']) : $row['code'],
                'status' => 'active',
            ]);
            $used[strtoupper($row['code'])] = true;
        }
    }

    public function generatePayrollLines(int $periodId): int
    {
        $period = (new PayrollPeriod())->find($periodId);
        if (!$period || ($period['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('payroll_not_draft'));
        }
        $companyId = (int) ($period['company_id'] ?? 0);
        $year = (int) ($period['period_year'] ?? 0);
        $month = (int) ($period['period_month'] ?? 0);
        if ($companyId < 1 || $year < 1 || $month < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $employees = (new Employee())->query(
            "SELECT id, salary_base FROM rateb_employees
             WHERE company_id = :cid AND status = 'active'",
            ['cid' => $companyId]
        );

        $lineModel = new PayrollLine();
        $created = 0;
        foreach ($employees as $emp) {
            $employeeId = (int) ($emp['id'] ?? 0);
            if ($employeeId < 1) {
                continue;
            }
            $existing = $lineModel->queryOne(
                'SELECT id FROM rateb_payroll_lines WHERE period_id = :pid AND employee_id = :eid LIMIT 1',
                ['pid' => $periodId, 'eid' => $employeeId]
            );
            if ($existing) {
                continue;
            }

            $basic = (float) ($emp['salary_base'] ?? 0);
            $absentDays = (int) ((new AttendanceRecord())->queryOne(
                "SELECT COUNT(*) AS c FROM rateb_attendance_records
                 WHERE company_id = :cid AND employee_id = :eid
                   AND attendance_date BETWEEN :s AND :e AND status = 'absent'",
                ['cid' => $companyId, 'eid' => $employeeId, 's' => $start, 'e' => $end]
            )['c'] ?? 0);

            $daily = $basic > 0 ? $basic / 30 : 0;
            $absentDeduction = round($daily * $absentDays, 2);
            $structure = $this->payrollStructureTotals($companyId, $employeeId, $basic);
            $loanDeduction = $this->loanInstallmentDeduction($companyId, $employeeId, $start, $end);
            $allowances = $structure['allowances'];
            $deductions = round($structure['deductions'] + $loanDeduction + $absentDeduction, 2);
            $net = max(0, round($basic + $allowances - $deductions, 2));

            $notes = [];
            if ($absentDays > 0) {
                $notes[] = __('payroll_absence_deduction', ['days' => $absentDays]);
            }
            if ($loanDeduction > 0) {
                $notes[] = __('payroll_loan_deduction', ['amount' => number_format($loanDeduction, 2)]);
            }

            $lineModel->create([
                'company_id' => $companyId,
                'period_id' => $periodId,
                'employee_id' => $employeeId,
                'basic_salary' => $basic,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net_salary' => $net,
                'notes' => $notes !== [] ? implode(' | ', $notes) : null,
            ]);
            $created++;
        }
        return $created;
    }

    public function approvePayroll(int $periodId): void
    {
        $period = (new PayrollPeriod())->find($periodId);
        if (!$period || ($period['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('payroll_not_draft'));
        }
        (new PayrollPeriod())->update($periodId, ['status' => 'approved']);
    }

    public function postPayroll(int $periodId): void
    {
        $period = (new PayrollPeriod())->find($periodId);
        if (!$period || ($period['status'] ?? '') !== 'approved') {
            throw new \RuntimeException(__('payroll_not_approved'));
        }
        (new PayrollPeriod())->update($periodId, ['status' => 'posted']);
    }

    public function approveLeave(int $requestId, int $userId): void
    {
        $req = (new LeaveRequest())->find($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            throw new \RuntimeException(__('leave_not_pending'));
        }
        (new LeaveRequest())->update($requestId, [
            'status' => 'approved',
            'approved_by' => $userId > 0 ? $userId : null,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        $this->applyApprovedLeave($req);
    }

    public function rejectLeave(int $requestId, int $userId): void
    {
        $req = (new LeaveRequest())->find($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            throw new \RuntimeException(__('leave_not_pending'));
        }
        (new LeaveRequest())->update($requestId, [
            'status' => 'rejected',
            'approved_by' => $userId > 0 ? $userId : null,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function bootstrapTenant(): void
    {
        $companyId = TenantContext::companyId();
        if ($companyId !== null && $companyId > 0) {
            $svc = new self();
            $svc->ensureDefaultLeaveTypes($companyId);
            $svc->ensureDefaultJobTitles($companyId);
        }
    }

    /** @return array{present: int, absent: int, on_leave: int}|null */
    public function employeeAttendanceYtd(int $employeeId): ?array
    {
        $emp = (new Employee())->find($employeeId);
        if (!$emp) {
            return null;
        }
        $companyId = (int) ($emp['company_id'] ?? 0);
        $year = (int) date('Y');
        $attendance = (new AttendanceRecord())->queryOne(
            "SELECT SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS on_leave
             FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid AND YEAR(attendance_date) = :y",
            ['cid' => $companyId, 'eid' => $employeeId, 'y' => $year]
        );
        return [
            'present' => (int) ($attendance['present'] ?? 0),
            'absent' => (int) ($attendance['absent'] ?? 0),
            'on_leave' => (int) ($attendance['on_leave'] ?? 0),
        ];
    }

    /**
     * Single reusable lookup for one employee's attendance on a calendar date.
     * SQL matches Offline HrOfflineTenantGuard (canonical predicate).
     *
     * @return array<string, mixed>|null
     */
    public function findAttendanceByEmployeeDate(int $companyId, int $employeeId, string $date): ?array
    {
        if ($companyId < 1 || $employeeId < 1 || $date === '') {
            return null;
        }

        return (new AttendanceRecord())->queryOne(
            'SELECT * FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid AND attendance_date = :d
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'd' => $date]
        );
    }

    /** @return array<string, mixed>|null */
    public function employeeProfile(int $employeeId): ?array
    {
        $emp = (new Employee())->find($employeeId);
        if (!$emp) {
            return null;
        }
        $companyId = (int) ($emp['company_id'] ?? 0);
        $year = (int) date('Y');
        $attendance = $this->employeeAttendanceYtd($employeeId);
        $leaves = (new LeaveRequest())->query(
            "SELECT lr.*, lt.name AS leave_type_name, lt.code AS leave_type_code, lr.leave_type_id
             FROM rateb_leave_requests lr
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.company_id = :cid AND lr.employee_id = :eid
             ORDER BY lr.start_date DESC LIMIT 10",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $leaves = $this->localizeLeaveTypeNames($leaves);
        $balances = $this->leaveBalancesForEmployee($employeeId, $year);
        return [
            'employee' => $emp,
            'attendance_ytd' => $attendance ?? ['present' => 0, 'absent' => 0, 'on_leave' => 0],
            'recent_leaves' => $leaves,
            'leave_balances' => $balances,
        ];
    }

    /** @return array<string, mixed>|null */
    public function fleetVehicleDetail(int $vehicleId): ?array
    {
        $vehicle = (new HrFleetVehicle())->find($vehicleId);
        if (!$vehicle) {
            return null;
        }
        $companyId = (int) ($vehicle['company_id'] ?? 0);
        $company = (new Company())->find($companyId);
        $lookup = new FormLookupService();
        $empId = (int) ($vehicle['assigned_employee_id'] ?? 0);
        $employee = null;
        $departmentName = '';
        if ($empId > 0) {
            $employee = (new Employee())->find($empId);
            if ($employee) {
                $departmentName = $lookup->resolveFkLabel('hr_departments', $employee['department_id'] ?? 0);
            }
        }
        return [
            'vehicle' => $vehicle,
            'company' => $company ?: [],
            'company_name' => (string) ($company['name'] ?? ''),
            'assigned_employee_name' => $lookup->resolveFkLabel('employees', $empId),
            'department_name' => $departmentName,
            'employee' => $employee,
            'status_label' => __((string) ($vehicle['status'] ?? 'active')),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function leaveBalancesForEmployee(int $employeeId, int $year): array
    {
        $emp = (new Employee())->find($employeeId);
        if (!$emp) {
            return [];
        }
        $companyId = (int) ($emp['company_id'] ?? 0);
        $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);
        return $this->localizeLeaveTypeNames((new LeaveBalance())->query(
            "SELECT lb.*, lt.name AS leave_type_name, lt.code AS leave_type_code, lb.leave_type_id, lt.days_per_year,
                    (lb.entitled_days - lb.used_days) AS remaining_days
             FROM rateb_leave_balances lb
             JOIN rateb_leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.company_id = :cid AND lb.employee_id = :eid AND lb.balance_year = :y
             ORDER BY FIELD(LOWER(lt.code),
                 'annual','sick','emergency','unpaid','hajj','marriage','bereavement',
                 'paternity','maternity','iddah','study','exam','compensatory','work_injury'
             ), lt.name ASC",
            ['cid' => $companyId, 'eid' => $employeeId, 'y' => $year]
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function leaveBalancesSummary(int $companyId, int $year): array
    {
        if ($companyId < 1) {
            return [];
        }
        $employees = (new Employee())->query(
            "SELECT id FROM rateb_employees WHERE company_id = :cid AND status = 'active'",
            ['cid' => $companyId]
        );
        foreach ($employees as $row) {
            $this->syncLeaveBalancesForEmployee($companyId, (int) $row['id'], $year);
        }
        return $this->localizeLeaveTypeNames((new LeaveBalance())->query(
            "SELECT lb.*, e.name AS employee_name, e.employee_code, lt.name AS leave_type_name,
                    lt.code AS leave_type_code, lb.leave_type_id
             FROM rateb_leave_balances lb
             JOIN rateb_employees e ON e.id = lb.employee_id
             JOIN rateb_leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.company_id = :cid AND lb.balance_year = :y
             ORDER BY e.name ASC, lt.name ASC",
            ['cid' => $companyId, 'y' => $year]
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function leaveReport(int $companyId, int $year): array
    {
        if ($companyId < 1) {
            return [];
        }
        return $this->localizeLeaveTypeNames((new LeaveRequest())->query(
            "SELECT e.employee_code, e.name AS employee_name, lt.name AS leave_type,
                    lt.code AS leave_type_code, lt.id AS leave_type_id,
                    SUM(lr.days) AS total_days,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM rateb_leave_requests lr
             JOIN rateb_employees e ON e.id = lr.employee_id
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.company_id = :cid
               AND YEAR(lr.start_date) = :y
             GROUP BY e.id, e.employee_code, e.name, lt.id, lt.name, lt.code
             ORDER BY e.name ASC, lt.name ASC",
            ['cid' => $companyId, 'y' => $year]
        ), 'leave_type');
    }

    /** @return array{attendance: array<int, array<string, mixed>>, payroll: array<int, array<string, mixed>>} */
    public function monthlyReport(int $companyId, int $year, int $month): array
    {
        if ($companyId < 1) {
            return ['attendance' => [], 'payroll' => []];
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $attendance = (new Employee())->query(
            "SELECT e.employee_code, e.name,
                    SUM(CASE WHEN ar.status IN ('present','late') THEN 1 ELSE 0 END) AS present_days,
                    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                    SUM(CASE WHEN ar.status = 'leave' THEN 1 ELSE 0 END) AS leave_days
             FROM rateb_employees e
             LEFT JOIN rateb_attendance_records ar
               ON ar.employee_id = e.id AND ar.company_id = e.company_id
              AND ar.attendance_date BETWEEN :s AND :e
             WHERE e.company_id = :cid AND e.status = 'active'
             GROUP BY e.id, e.employee_code, e.name
             ORDER BY e.name ASC",
            ['cid' => $companyId, 's' => $start, 'e' => $end]
        );
        $payroll = (new PayrollLine())->query(
            "SELECT e.employee_code, e.name, pl.basic_salary, pl.deductions, pl.net_salary, pp.status
             FROM rateb_payroll_lines pl
             JOIN rateb_payroll_periods pp ON pp.id = pl.period_id
             JOIN rateb_employees e ON e.id = pl.employee_id
             WHERE pl.company_id = :cid AND pp.period_year = :y AND pp.period_month = :m
             ORDER BY e.name ASC",
            ['cid' => $companyId, 'y' => $year, 'm' => $month]
        );
        return ['attendance' => $attendance, 'payroll' => $payroll];
    }

    private function syncLeaveBalancesForEmployee(int $companyId, int $employeeId, int $year): void
    {
        $types = (new LeaveType())->query(
            'SELECT id, days_per_year FROM rateb_leave_types WHERE company_id = :cid AND status = \'active\'',
            ['cid' => $companyId]
        );
        $balanceModel = new LeaveBalance();
        foreach ($types as $type) {
            $typeId = (int) ($type['id'] ?? 0);
            if ($typeId < 1) {
                continue;
            }
            $existing = $balanceModel->queryOne(
                'SELECT id FROM rateb_leave_balances
                 WHERE company_id = :cid AND employee_id = :eid AND leave_type_id = :tid AND balance_year = :y LIMIT 1',
                ['cid' => $companyId, 'eid' => $employeeId, 'tid' => $typeId, 'y' => $year]
            );
            $used = (float) ((new LeaveRequest())->queryOne(
                "SELECT COALESCE(SUM(days), 0) AS d FROM rateb_leave_requests
                 WHERE company_id = :cid AND employee_id = :eid AND leave_type_id = :tid
                   AND status = 'approved' AND YEAR(start_date) = :y",
                ['cid' => $companyId, 'eid' => $employeeId, 'tid' => $typeId, 'y' => $year]
            )['d'] ?? 0);
            $entitled = (float) ($type['days_per_year'] ?? 0);
            if ($existing) {
                $balanceModel->update((int) $existing['id'], [
                    'entitled_days' => $entitled,
                    'used_days' => $used,
                ]);
            } else {
                $balanceModel->create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'leave_type_id' => $typeId,
                    'balance_year' => $year,
                    'entitled_days' => $entitled,
                    'used_days' => $used,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $req */
    private function applyApprovedLeave(array $req): void
    {
        $companyId = (int) ($req['company_id'] ?? 0);
        $employeeId = (int) ($req['employee_id'] ?? 0);
        $start = (string) ($req['start_date'] ?? '');
        $end = (string) ($req['end_date'] ?? '');
        if ($companyId < 1 || $employeeId < 1 || $start === '' || $end === '') {
            return;
        }
        $year = (int) date('Y', strtotime($start));
        $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);

        $attModel = new AttendanceRecord();
        $cursor = strtotime($start);
        $endTs = strtotime($end);
        if ($cursor === false || $endTs === false) {
            return;
        }
        while ($cursor <= $endTs) {
            $date = date('Y-m-d', $cursor);
            $exists = $this->findAttendanceByEmployeeDate($companyId, $employeeId, $date);
            if (!$exists) {
                $attModel->create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'status' => 'leave',
                    'notes' => __('hr_leave_auto_attendance'),
                ]);
            }
            $cursor = strtotime('+1 day', $cursor);
        }
    }

    /** @return array{allowances: float, deductions: float} */
    public function payrollStructureTotals(int $companyId, int $employeeId, float $basicSalary): array
    {
        $rows = (new HrPayrollStructure())->query(
            "SELECT ps.value, pc.component_type, pc.calc_type
             FROM rateb_hr_payroll_structures ps
             JOIN rateb_hr_payroll_components pc ON pc.id = ps.component_id
             WHERE ps.company_id = :cid AND ps.employee_id = :eid AND pc.status = 'active'",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $allowances = 0.0;
        $deductions = 0.0;
        foreach ($rows as $row) {
            $amount = $this->payrollComponentAmount($row, $basicSalary);
            if (($row['component_type'] ?? '') === 'allowance') {
                $allowances += $amount;
            } else {
                $deductions += $amount;
            }
        }
        return [
            'allowances' => round($allowances, 2),
            'deductions' => round($deductions, 2),
        ];
    }

    /** @param array<string, mixed> $row */
    private function payrollComponentAmount(array $row, float $basicSalary): float
    {
        $value = (float) ($row['value'] ?? 0);
        if (($row['calc_type'] ?? 'fixed') === 'percent') {
            return round($basicSalary * $value / 100, 2);
        }
        return round($value, 2);
    }

    public function loanInstallmentDeduction(int $companyId, int $employeeId, string $periodStart, string $periodEnd): float
    {
        $row = (new HrLoan())->queryOne(
            "SELECT COALESCE(SUM(installment_amount), 0) AS total
             FROM rateb_hr_loans
             WHERE company_id = :cid AND employee_id = :eid AND status = 'active'
               AND paid_installments < installments_count
               AND start_date <= :end",
            ['cid' => $companyId, 'eid' => $employeeId, 'end' => $periodEnd]
        );
        return round((float) ($row['total'] ?? 0), 2);
    }

    public function syncHolidayAttendance(int $companyId, string $holidayDate, string $holidayName = ''): int
    {
        if ($companyId < 1 || $holidayDate === '') {
            return 0;
        }
        $employees = (new Employee())->query(
            "SELECT id FROM rateb_employees WHERE company_id = :cid AND status = 'active'",
            ['cid' => $companyId]
        );
        $attModel = new AttendanceRecord();
        $note = $holidayName !== '' ? $holidayName : __('holiday');
        $synced = 0;
        foreach ($employees as $emp) {
            $employeeId = (int) ($emp['id'] ?? 0);
            if ($employeeId < 1) {
                continue;
            }
            $existing = $this->findAttendanceByEmployeeDate($companyId, $employeeId, $holidayDate);
            if ($existing && in_array((string) ($existing['status'] ?? ''), ['present', 'late'], true)) {
                continue;
            }
            if ($existing) {
                $attModel->update((int) $existing['id'], [
                    'status' => 'holiday',
                    'notes' => $note,
                ]);
            } else {
                $attModel->create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $holidayDate,
                    'status' => 'holiday',
                    'notes' => $note,
                ]);
            }
            $synced++;
        }
        return $synced;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function localizeLeaveTypeNames(array $rows, string $field = 'leave_type_name'): array
    {
        $lookup = new FormLookupService();
        foreach ($rows as &$row) {
            $row[$field] = $lookup->localizeLeaveType([
                'code' => $row['leave_type_code'] ?? '',
                'name' => $row[$field] ?? '',
                'id' => $row['leave_type_id'] ?? 0,
            ]);
        }
        unset($row);
        return $rows;
    }
}
