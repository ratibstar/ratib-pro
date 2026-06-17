<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\Employee;
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

    public function ensureDefaultLeaveTypes(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $count = (new LeaveType())->count(['company_id' => $companyId]);
        if ($count > 0) {
            return;
        }
        $defaults = [
            ['name' => 'Annual leave', 'paid' => 1, 'days_per_year' => 21],
            ['name' => 'Sick leave', 'paid' => 1, 'days_per_year' => 30],
            ['name' => 'Unpaid leave', 'paid' => 0, 'days_per_year' => null],
        ];
        $model = new LeaveType();
        foreach ($defaults as $row) {
            $model->create([
                'company_id' => $companyId,
                'name' => $row['name'],
                'paid' => $row['paid'],
                'days_per_year' => $row['days_per_year'],
                'status' => 'active',
            ]);
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
            $deductions = round($daily * $absentDays, 2);
            $net = max(0, round($basic - $deductions, 2));

            $lineModel->create([
                'company_id' => $companyId,
                'period_id' => $periodId,
                'employee_id' => $employeeId,
                'basic_salary' => $basic,
                'allowances' => 0,
                'deductions' => $deductions,
                'net_salary' => $net,
                'notes' => $absentDays > 0 ? __('payroll_absence_deduction', ['days' => $absentDays]) : null,
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
            (new self())->ensureDefaultLeaveTypes($companyId);
        }
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
        $attendance = (new AttendanceRecord())->queryOne(
            "SELECT SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS on_leave
             FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid AND YEAR(attendance_date) = :y",
            ['cid' => $companyId, 'eid' => $employeeId, 'y' => $year]
        );
        $leaves = (new LeaveRequest())->query(
            "SELECT lr.*, lt.name AS leave_type_name
             FROM rateb_leave_requests lr
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.company_id = :cid AND lr.employee_id = :eid
             ORDER BY lr.start_date DESC LIMIT 10",
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        $balances = $this->leaveBalancesForEmployee($employeeId, $year);
        return [
            'employee' => $emp,
            'attendance_ytd' => $attendance ?: ['present' => 0, 'absent' => 0, 'on_leave' => 0],
            'recent_leaves' => $leaves,
            'leave_balances' => $balances,
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
        return (new LeaveBalance())->query(
            "SELECT lb.*, lt.name AS leave_type_name, lt.days_per_year
             FROM rateb_leave_balances lb
             JOIN rateb_leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.company_id = :cid AND lb.employee_id = :eid AND lb.balance_year = :y
             ORDER BY lt.name ASC",
            ['cid' => $companyId, 'eid' => $employeeId, 'y' => $year]
        );
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
        return (new LeaveBalance())->query(
            "SELECT lb.*, e.name AS employee_name, e.employee_code, lt.name AS leave_type_name
             FROM rateb_leave_balances lb
             JOIN rateb_employees e ON e.id = lb.employee_id
             JOIN rateb_leave_types lt ON lt.id = lb.leave_type_id
             WHERE lb.company_id = :cid AND lb.balance_year = :y
             ORDER BY e.name ASC, lt.name ASC",
            ['cid' => $companyId, 'y' => $year]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function leaveReport(int $companyId, int $year): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new LeaveRequest())->query(
            "SELECT e.employee_code, e.name AS employee_name, lt.name AS leave_type,
                    SUM(lr.days) AS total_days,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
             FROM rateb_leave_requests lr
             JOIN rateb_employees e ON e.id = lr.employee_id
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.company_id = :cid
               AND YEAR(lr.start_date) = :y
             GROUP BY e.id, e.employee_code, e.name, lt.id, lt.name
             ORDER BY e.name ASC, lt.name ASC",
            ['cid' => $companyId, 'y' => $year]
        );
    }

    /** @return array{attendance: array<int, array<string, mixed>>, payroll: array<int, array<string, mixed>>} */
    public function monthlyReport(int $companyId, int $year, int $month): array
    {
        if ($companyId < 1) {
            return ['attendance' => [], 'payroll' => []];
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $attendance = (new AttendanceRecord())->query(
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
            $exists = $attModel->queryOne(
                'SELECT id FROM rateb_attendance_records
                 WHERE company_id = :cid AND employee_id = :eid AND attendance_date = :d LIMIT 1',
                ['cid' => $companyId, 'eid' => $employeeId, 'd' => $date]
            );
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
}
