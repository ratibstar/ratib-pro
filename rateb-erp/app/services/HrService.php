<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\Employee;
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
}
