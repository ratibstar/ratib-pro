<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
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
use Rateb\App\Models\PayrollAudit;
use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPeriod;
use PDO;

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

    /**
     * Build draft payroll lines for a period.
     *
     * Formula (unchanged core):
     *   net = max(0, salary_base + structure_allowances - (structure_deductions + loans + deduct_days*(salary_base/30)))
     * Absence input: rateb_attendance_records.status = 'absent' within calendar month.
     * Leave days (status=leave) are NOT deducted as absence; paid leave stays non-deductible.
     * Phase H2: unpaid approved leave days (paid_snapshot=0) batch-added to deduct_days — same /30 rate.
     * Paid leave (attendance status=leave, paid_snapshot=1) is NOT deducted.
     * Enterprise salary overlay is NOT used.
     * Phase D: batch-load absence / structure / loan inputs (same totals, no N+1).
     */
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
        if (!is_array($employees) || $employees === []) {
            return 0;
        }

        $lineModel = new PayrollLine();
        $existingRows = $lineModel->query(
            'SELECT employee_id FROM rateb_payroll_lines WHERE period_id = :pid AND company_id = :cid',
            ['pid' => $periodId, 'cid' => $companyId]
        );
        $existingEmployeeIds = [];
        foreach (is_array($existingRows) ? $existingRows : [] as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid > 0) {
                $existingEmployeeIds[$eid] = true;
            }
        }

        $absentByEmployee = $this->batchAbsenceDaysByEmployee($companyId, $start, $end);
        $unpaidLeaveByEmployee = $this->batchUnpaidLeaveDaysByEmployee($companyId, $start, $end);
        $structureRowsByEmployee = $this->batchPayrollStructureRows($companyId);
        $loanByEmployee = $this->batchLoanInstallmentsByEmployee($companyId, $end);

        $created = 0;
        foreach ($employees as $emp) {
            $employeeId = (int) ($emp['id'] ?? 0);
            if ($employeeId < 1 || isset($existingEmployeeIds[$employeeId])) {
                continue;
            }

            $basic = (float) ($emp['salary_base'] ?? 0);
            $absentDays = (int) ($absentByEmployee[$employeeId] ?? 0);
            $unpaidLeaveDays = (int) ($unpaidLeaveByEmployee[$employeeId] ?? 0);
            $deductDays = $absentDays + $unpaidLeaveDays;
            $daily = $basic > 0 ? $basic / 30 : 0;
            $absentDeduction = round($daily * $deductDays, 2);
            $structure = $this->payrollStructureTotalsFromRows(
                $structureRowsByEmployee[$employeeId] ?? [],
                $basic
            );
            $loanDeduction = (float) ($loanByEmployee[$employeeId] ?? 0.0);
            $allowances = $structure['allowances'];
            $deductions = round($structure['deductions'] + $loanDeduction + $absentDeduction, 2);
            $net = max(0, round($basic + $allowances - $deductions, 2));

            $notes = [];
            if ($absentDays > 0) {
                $notes[] = __('payroll_absence_deduction', ['days' => $absentDays]);
            }
            if ($unpaidLeaveDays > 0) {
                $notes[] = __('payroll_unpaid_leave_deduction', ['days' => $unpaidLeaveDays]);
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
        if ($created > 0) {
            $this->recordPayrollAudit('calculated', $period, (string) ($period['status'] ?? 'draft'), (string) ($period['status'] ?? 'draft'), [
                'lines_created' => $created,
                'period_start' => $start,
                'period_end' => $end,
                'gl_posted' => false,
                'bank_transfer' => false,
            ]);
        }
        return $created;
    }

    /**
     * @return array<int, int> employee_id => absent day count in [start,end]
     */
    private function batchAbsenceDaysByEmployee(int $companyId, string $start, string $end): array
    {
        $rows = (new AttendanceRecord())->query(
            "SELECT employee_id, COUNT(*) AS c FROM rateb_attendance_records
             WHERE company_id = :cid
               AND attendance_date BETWEEN :s AND :e
               AND status = 'absent'
             GROUP BY employee_id",
            ['cid' => $companyId, 's' => $start, 'e' => $end]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid > 0) {
                $out[$eid] = (int) ($row['c'] ?? 0);
            }
        }
        return $out;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function batchPayrollStructureRows(int $companyId): array
    {
        $rows = (new HrPayrollStructure())->query(
            "SELECT ps.employee_id, ps.value, pc.component_type, pc.calc_type
             FROM rateb_hr_payroll_structures ps
             JOIN rateb_hr_payroll_components pc ON pc.id = ps.component_id
             WHERE ps.company_id = :cid AND pc.status = 'active'",
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            $out[$eid][] = $row;
        }
        return $out;
    }

    /**
     * @return array<int, float>
     */
    private function batchLoanInstallmentsByEmployee(int $companyId, string $periodEnd): array
    {
        $rows = (new HrLoan())->query(
            "SELECT employee_id, COALESCE(SUM(installment_amount), 0) AS total
             FROM rateb_hr_loans
             WHERE company_id = :cid AND status = 'active'
               AND paid_installments < installments_count
               AND start_date <= :end
             GROUP BY employee_id",
            ['cid' => $companyId, 'end' => $periodEnd]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid > 0) {
                $out[$eid] = round((float) ($row['total'] ?? 0), 2);
            }
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{allowances: float, deductions: float}
     */
    private function payrollStructureTotalsFromRows(array $rows, float $basicSalary): array
    {
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

    /**
     * Draft → approved. Optional $expectedCompanyId enforces tenant isolation (IDOR guard).
     */
    public function approvePayroll(int $periodId, ?int $expectedCompanyId = null): void
    {
        $period = $this->loadPayrollPeriodForMutation($periodId, $expectedCompanyId);
        if (($period['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('payroll_not_draft'));
        }
        (new PayrollPeriod())->update($periodId, ['status' => 'approved']);
        $this->recordPayrollAudit('approved', $period, 'draft', 'approved');
    }

    /**
     * Approved → posted only. Cannot bypass approval (draft/rejected → posted denied).
     * Optional $expectedCompanyId enforces tenant isolation (IDOR guard).
     *
     * Phase D: status lock only by default — does NOT create GL journals or bank transfers.
     * Phase E: when HR_PAYROLL_ACCOUNTING_ENABLED=true, optionally creates a DRAFT journal
     * via HrPayrollAccountingAdapter (AccountingService). Flag defaults OFF.
     * Idempotent: already-posted periods return without a second payroll audit row;
     * adapter itself is idempotent for journals.
     */
    public function postPayroll(int $periodId, ?int $expectedCompanyId = null): void
    {
        $period = $this->loadPayrollPeriodForMutation($periodId, $expectedCompanyId);
        $status = (string) ($period['status'] ?? '');
        if ($status === 'posted') {
            // Idempotent lock — optional accounting retry when flag ON.
            if (HrPayrollAccountingConfig::isEnabled()) {
                (new HrPayrollAccountingAdapter())->ensureDraftJournal($periodId, $expectedCompanyId);
            }
            return;
        }
        if ($status !== 'approved') {
            throw new \RuntimeException(__('payroll_not_approved'));
        }
        (new PayrollPeriod())->update($periodId, ['status' => 'posted']);
        $flagOn = HrPayrollAccountingConfig::isEnabled();
        $this->recordPayrollAudit('posted', $period, 'approved', 'posted', [
            'gl_posted' => false,
            'bank_transfer' => false,
            'note' => 'payroll_status_lock_only',
            'accounting_flag_enabled' => $flagOn,
        ]);
        if ($flagOn) {
            // Best-effort draft journal; payroll remains posted even if accounting fails.
            (new HrPayrollAccountingAdapter())->ensureDraftJournal($periodId, $expectedCompanyId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPayrollPeriodForMutation(int $periodId, ?int $expectedCompanyId): array
    {
        $period = (new PayrollPeriod())->findByIdUnscoped($periodId);
        if (!is_array($period)) {
            throw new \RuntimeException(__('record_not_found'));
        }
        $periodCompanyId = (int) ($period['company_id'] ?? 0);
        $cid = $expectedCompanyId !== null && $expectedCompanyId > 0
            ? $expectedCompanyId
            : (int) (TenantContext::companyId() ?? 0);
        if ($cid > 0 && $periodCompanyId !== $cid) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($cid < 1 && function_exists('rateb_is_super_admin') && !rateb_is_super_admin()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        return $period;
    }

    /**
     * Append-only payroll audit: rateb_payroll_audit + rateb_audit_logs.
     *
     * @param array<string, mixed> $period
     * @param array<string, mixed> $extra
     */
    private function recordPayrollAudit(
        string $action,
        array $period,
        string $fromStatus,
        string $toStatus,
        array $extra = []
    ): void {
        $periodId = (int) ($period['id'] ?? 0);
        $companyId = (int) ($period['company_id'] ?? 0);
        $payload = array_merge([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'period_year' => $period['period_year'] ?? null,
            'period_month' => $period['period_month'] ?? null,
            'source' => 'hr_ops_payroll',
        ], $extra);

        try {
            (new AuditService())->log('payroll_' . $action, 'hr_payroll_period', $periodId, $payload);
        } catch (\Throwable $e) {
            // Audit must not block payroll mutation.
        }

        if ($companyId < 1 || $periodId < 1) {
            return;
        }
        try {
            $uid = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0);
            (new PayrollAudit())->create([
                'public_uuid' => $this->payrollAuditUuid(),
                'company_id' => $companyId,
                'branch_id' => isset($period['branch_id']) ? (int) $period['branch_id'] : null,
                'entity_type' => 'hr_payroll_period',
                'entity_id' => $periodId,
                'action' => $action,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'version' => 1,
                'created_by' => $uid > 0 ? $uid : null,
                'updated_by' => $uid > 0 ? $uid : null,
            ]);
        } catch (\Throwable $e) {
            // Best-effort: schema may lag on older tenants until migration 190 applied.
        }
    }

    private function payrollAuditUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function approveLeave(int $requestId, int $userId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'SELECT * FROM rateb_leave_requests WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req || ($req['status'] ?? '') !== 'pending') {
                throw new \RuntimeException(__('leave_not_pending'));
            }
            $companyId = (int) ($req['company_id'] ?? 0);
            $type = (new LeaveType())->queryOne(
                'SELECT id, paid FROM rateb_leave_types WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => (int) ($req['leave_type_id'] ?? 0), 'cid' => $companyId]
            );
            $paidSnapshot = (int) ($type['paid'] ?? 1) === 1 ? 1 : 0;
            $hasPaidSnap = $this->leaveRequestHasPaidSnapshotColumn();
            if ($hasPaidSnap) {
                $upd = $db->prepare(
                    'UPDATE rateb_leave_requests
                     SET status = \'approved\', approved_by = :uid, approved_at = NOW(), paid_snapshot = :paid
                     WHERE id = :id AND status = \'pending\''
                );
                $upd->execute([
                    'uid' => $userId > 0 ? $userId : null,
                    'paid' => $paidSnapshot,
                    'id' => $requestId,
                ]);
            } else {
                $upd = $db->prepare(
                    'UPDATE rateb_leave_requests
                     SET status = \'approved\', approved_by = :uid, approved_at = NOW()
                     WHERE id = :id AND status = \'pending\''
                );
                $upd->execute([
                    'uid' => $userId > 0 ? $userId : null,
                    'id' => $requestId,
                ]);
            }
            if ($upd->rowCount() < 1) {
                throw new \RuntimeException(__('leave_not_pending'));
            }
            $req['paid_snapshot'] = $paidSnapshot;
            $req['status'] = 'approved';
            $this->applyApprovedLeave($req, $requestId);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->auditLeave('leave_approved', $requestId, [
            'company_id' => (int) ($req['company_id'] ?? 0),
            'paid_snapshot' => (int) ($req['paid_snapshot'] ?? 1),
            'days' => (float) ($req['days'] ?? 0),
        ]);
        $this->auditLeave('balance_consumed', $requestId, [
            'company_id' => (int) ($req['company_id'] ?? 0),
            'days' => (float) ($req['days'] ?? 0),
        ]);
        if ((int) ($req['paid_snapshot'] ?? 1) === 0) {
            $this->auditLeave('unpaid_leave_payroll_input', $requestId, [
                'company_id' => (int) ($req['company_id'] ?? 0),
                'note' => 'paid_snapshot=0; payroll deducts via batch unpaid leave days',
            ]);
        }
        $this->notifyLeaveOutcome((int) ($req['company_id'] ?? 0), (int) ($req['employee_id'] ?? 0), $requestId, 'approved');
    }

    public function rejectLeave(int $requestId, int $userId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM rateb_leave_requests WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req || ($req['status'] ?? '') !== 'pending') {
                throw new \RuntimeException(__('leave_not_pending'));
            }
            $upd = $db->prepare(
                'UPDATE rateb_leave_requests
                 SET status = \'rejected\', approved_by = :uid, approved_at = NOW()
                 WHERE id = :id AND status = \'pending\''
            );
            $upd->execute(['uid' => $userId > 0 ? $userId : null, 'id' => $requestId]);
            if ($upd->rowCount() < 1) {
                throw new \RuntimeException(__('leave_not_pending'));
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $this->auditLeave('leave_rejected', $requestId, [
            'company_id' => (int) ($req['company_id'] ?? 0),
        ]);
        $this->notifyLeaveOutcome((int) ($req['company_id'] ?? 0), (int) ($req['employee_id'] ?? 0), $requestId, 'rejected');
    }

    /**
     * Safe cancellation: pending|approved → cancelled. Restores balance once; reverses leave-owned attendance only.
     */
    public function cancelLeave(int $requestId, int $userId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM rateb_leave_requests WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $status = (string) ($req['status'] ?? '');
            if ($status === 'cancelled') {
                $db->commit();
                return;
            }
            if ($status === 'rejected') {
                throw new \RuntimeException(__('invalid_request'));
            }
            if (!in_array($status, ['pending', 'approved'], true)) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $companyId = (int) ($req['company_id'] ?? 0);
            $employeeId = (int) ($req['employee_id'] ?? 0);
            $start = (string) ($req['start_date'] ?? '');
            $end = (string) ($req['end_date'] ?? '');
            if ($status === 'approved' && $this->hasPostedPayrollOverlappingLeave($companyId, $employeeId, $start, $end)) {
                throw new \RuntimeException(__('leave_cancel_blocked_posted_payroll'));
            }
            if ($status === 'approved') {
                $this->reverseLeaveOwnedAttendance($companyId, $requestId);
            }
            $upd = $db->prepare(
                'UPDATE rateb_leave_requests
                 SET status = \'cancelled\', approved_by = :uid, approved_at = NOW()
                 WHERE id = :id AND status IN (\'pending\', \'approved\')'
            );
            $upd->execute(['uid' => $userId > 0 ? $userId : null, 'id' => $requestId]);
            if ($upd->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
            }
            if ($status === 'approved') {
                $year = (int) date('Y', strtotime($start) ?: time());
                $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $wasApproved = ((string) ($req['status'] ?? '')) === 'approved';
        $this->auditLeave('leave_cancelled', $requestId, [
            'company_id' => (int) ($req['company_id'] ?? 0),
            'from_status' => (string) ($req['status'] ?? ''),
        ]);
        if ($wasApproved) {
            $this->auditLeave('balance_restored', $requestId, [
                'company_id' => (int) ($req['company_id'] ?? 0),
                'days' => (float) ($req['days'] ?? 0),
            ]);
        }
        $this->notifyLeaveOutcome((int) ($req['company_id'] ?? 0), (int) ($req['employee_id'] ?? 0), $requestId, 'cancelled');
    }

    /**
     * Oversight undo: approved → pending. Reverses leave-owned attendance; restores balance via sync.
     */
    public function undoLeaveApproval(int $requestId): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM rateb_leave_requests WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $status = (string) ($req['status'] ?? '');
            if ($status === 'pending') {
                $db->commit();
                return;
            }
            if ($status !== 'approved' && $status !== 'rejected') {
                throw new \RuntimeException(__('invalid_request'));
            }
            $companyId = (int) ($req['company_id'] ?? 0);
            $employeeId = (int) ($req['employee_id'] ?? 0);
            $start = (string) ($req['start_date'] ?? '');
            $end = (string) ($req['end_date'] ?? '');
            if ($status === 'approved' && $this->hasPostedPayrollOverlappingLeave($companyId, $employeeId, $start, $end)) {
                throw new \RuntimeException(__('leave_undo_blocked_posted_payroll'));
            }
            if ($status === 'approved') {
                $this->reverseLeaveOwnedAttendance($companyId, $requestId);
            }
            $upd = $db->prepare(
                'UPDATE rateb_leave_requests
                 SET status = \'pending\', approved_by = NULL, approved_at = NULL'
                . ($this->leaveRequestHasPaidSnapshotColumn() ? ', paid_snapshot = NULL' : '')
                . ' WHERE id = :id AND status IN (\'approved\', \'rejected\')'
            );
            $upd->execute(['id' => $requestId]);
            if ($upd->rowCount() < 1) {
                throw new \RuntimeException(__('invalid_request'));
            }
            if ($status === 'approved') {
                $year = (int) date('Y', strtotime($start) ?: time());
                $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $this->auditLeave('leave_undo', $requestId, [
            'company_id' => (int) ($req['company_id'] ?? 0),
            'from_status' => (string) ($req['status'] ?? ''),
        ]);
        if (((string) ($req['status'] ?? '')) === 'approved') {
            $this->auditLeave('balance_restored', $requestId, [
                'company_id' => (int) ($req['company_id'] ?? 0),
                'days' => (float) ($req['days'] ?? 0),
            ]);
        }
    }

    /**
     * Canonical pending leave create (ESS + Admin). Locks employee row; overlap + balance guards.
     *
     * @param array<string, mixed> $extra
     */
    public function createPendingLeaveRequest(
        int $companyId,
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $days,
        ?string $reason = null,
        ?int $branchId = null,
        ?int $actorUserId = null
    ): int {
        if ($companyId < 1 || $employeeId < 1 || $leaveTypeId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($startDate === '' || $endDate === '' || $endDate < $startDate || $days <= 0) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $empLock = $db->prepare(
                'SELECT id, branch_id FROM rateb_employees WHERE id = :id AND company_id = :cid FOR UPDATE'
            );
            $empLock->execute(['id' => $employeeId, 'cid' => $companyId]);
            $emp = $empLock->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $type = (new LeaveType())->queryOne(
                'SELECT id, status, days_per_year FROM rateb_leave_types
                 WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $leaveTypeId, 'cid' => $companyId]
            );
            if (!$type || strtolower((string) ($type['status'] ?? '')) === 'inactive') {
                throw new \RuntimeException(__('invalid_request'));
            }
            if ($this->hasOverlappingLeaveRequest($companyId, $employeeId, $startDate, $endDate)) {
                throw new \RuntimeException(__('leave_overlap_conflict'));
            }
            $year = (int) date('Y', strtotime($startDate) ?: time());
            $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);
            $bal = (new LeaveBalance())->queryOne(
                'SELECT entitled_days, used_days FROM rateb_leave_balances
                 WHERE company_id = :cid AND employee_id = :eid AND leave_type_id = :tid AND balance_year = :y
                 LIMIT 1',
                ['cid' => $companyId, 'eid' => $employeeId, 'tid' => $leaveTypeId, 'y' => $year]
            );
            $entitled = (float) ($bal['entitled_days'] ?? 0);
            $used = (float) ($bal['used_days'] ?? 0);
            $remaining = $entitled - $used;
            $daysPerYear = $type['days_per_year'];
            $hasCap = $daysPerYear !== null && $daysPerYear !== '' && (float) $daysPerYear > 0;
            if ($hasCap && $days > $remaining + 0.0001) {
                throw new \RuntimeException(__('leave_balance_insufficient'));
            }

            $bid = $branchId !== null && $branchId > 0 ? $branchId : (int) ($emp['branch_id'] ?? 0);
            $ins = $db->prepare(
                'INSERT INTO rateb_leave_requests
                    (company_id, employee_id, leave_type_id, start_date, end_date, days, reason, status, branch_id)
                 VALUES
                    (:cid, :eid, :tid, :s, :e, :days, :reason, \'pending\', :bid)'
            );
            $ins->execute([
                'cid' => $companyId,
                'eid' => $employeeId,
                'tid' => $leaveTypeId,
                's' => $startDate,
                'e' => $endDate,
                'days' => $days,
                'reason' => $reason !== null && $reason !== '' ? $reason : null,
                'bid' => $bid > 0 ? $bid : null,
            ]);
            $id = (int) $db->lastInsertId();
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->auditLeave('leave_created', $id, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'days' => $days,
            'actor_user_id' => $actorUserId,
        ]);
        $this->auditLeave('leave_submitted', $id, [
            'company_id' => $companyId,
        ]);
        return $id;
    }

    /**
     * @deprecated use createPendingLeaveRequest — kept for call-site clarity in validators
     */
    public function assertLeaveSubmissionAllowed(
        int $companyId,
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $days,
        bool $lock = true
    ): void {
        // Dry-run path without insert: reuse create guards via temporary check only.
        if ($companyId < 1 || $employeeId < 1 || $leaveTypeId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($startDate === '' || $endDate === '' || $endDate < $startDate || $days <= 0) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $type = (new LeaveType())->queryOne(
            'SELECT id, status, days_per_year FROM rateb_leave_types
             WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $leaveTypeId, 'cid' => $companyId]
        );
        if (!$type || strtolower((string) ($type['status'] ?? '')) === 'inactive') {
            throw new \RuntimeException(__('invalid_request'));
        }
        if ($this->hasOverlappingLeaveRequest($companyId, $employeeId, $startDate, $endDate)) {
            throw new \RuntimeException(__('leave_overlap_conflict'));
        }
        $year = (int) date('Y', strtotime($startDate) ?: time());
        $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);
        $bal = (new LeaveBalance())->queryOne(
            'SELECT entitled_days, used_days FROM rateb_leave_balances
             WHERE company_id = :cid AND employee_id = :eid AND leave_type_id = :tid AND balance_year = :y
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'tid' => $leaveTypeId, 'y' => $year]
        );
        $entitled = (float) ($bal['entitled_days'] ?? 0);
        $used = (float) ($bal['used_days'] ?? 0);
        $remaining = $entitled - $used;
        $daysPerYear = $type['days_per_year'];
        $hasCap = $daysPerYear !== null && $daysPerYear !== '' && (float) $daysPerYear > 0;
        if ($hasCap && $days > $remaining + 0.0001) {
            throw new \RuntimeException(__('leave_balance_insufficient'));
        }
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
            'SELECT id, company_id, employee_id, attendance_date, check_in, check_out, status, notes, branch_id
             FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid AND attendance_date = :d
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'd' => $date]
        );
    }

    /**
     * ESS attendance history — employee + company scoped date range.
     *
     * @return list<array<string, mixed>>
     */
    public function listAttendanceForEmployee(
        int $companyId,
        int $employeeId,
        string $fromDate,
        string $toDate
    ): array {
        if ($companyId < 1 || $employeeId < 1 || $fromDate === '' || $toDate === '') {
            return [];
        }

        return (new AttendanceRecord())->query(
            'SELECT id, company_id, employee_id, attendance_date, check_in, check_out, status, notes, branch_id
             FROM rateb_attendance_records
             WHERE company_id = :cid AND employee_id = :eid
               AND attendance_date BETWEEN :from_d AND :to_d
             ORDER BY attendance_date DESC',
            [
                'cid' => $companyId,
                'eid' => $employeeId,
                'from_d' => $fromDate,
                'to_d' => $toDate,
            ]
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
            "SELECT lb.id, lb.company_id, lb.employee_id, lb.leave_type_id, lb.balance_year,
                    lb.entitled_days, lb.used_days, lt.name AS leave_type_name, lt.code AS leave_type_code,
                    lt.days_per_year, (lb.entitled_days - lb.used_days) AS remaining_days
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

    /**
     * ESS leave request list — company + employee scoped.
     *
     * @return list<array<string, mixed>>
     */
    public function listLeaveRequestsForEmployee(
        int $companyId,
        int $employeeId,
        ?string $status = null
    ): array {
        if ($companyId < 1 || $employeeId < 1) {
            return [];
        }
        $sql = "SELECT lr.id, lr.company_id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date,
                       lr.days, lr.reason, lr.status, lr.created_at,
                       lt.name AS leave_type_name, lt.code AS leave_type_code
                FROM rateb_leave_requests lr
                JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id AND lt.company_id = lr.company_id
                WHERE lr.company_id = :cid AND lr.employee_id = :eid";
        $params = ['cid' => $companyId, 'eid' => $employeeId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND lr.status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY lr.start_date DESC, lr.id DESC LIMIT 100';

        return $this->localizeLeaveTypeNames((new LeaveRequest())->query($sql, $params));
    }

    /** @return array<string, mixed>|null */
    public function findLeaveRequestForEmployee(int $companyId, int $employeeId, int $requestId): ?array
    {
        if ($companyId < 1 || $employeeId < 1 || $requestId < 1) {
            return null;
        }

        $row = (new LeaveRequest())->queryOne(
            "SELECT lr.id, lr.company_id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date,
                    lr.days, lr.reason, lr.status, lr.created_at,
                    lt.name AS leave_type_name, lt.code AS leave_type_code
             FROM rateb_leave_requests lr
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id AND lt.company_id = lr.company_id
             WHERE lr.company_id = :cid AND lr.employee_id = :eid AND lr.id = :id
             LIMIT 1",
            ['cid' => $companyId, 'eid' => $employeeId, 'id' => $requestId]
        );
        if ($row === null) {
            return null;
        }
        $localized = $this->localizeLeaveTypeNames([$row]);

        return $localized[0] ?? $row;
    }

    /**
     * Overlap with pending/approved requests (duplicate protection for ESS apply).
     */
    public function hasOverlappingLeaveRequest(
        int $companyId,
        int $employeeId,
        string $startDate,
        string $endDate
    ): bool {
        if ($companyId < 1 || $employeeId < 1 || $startDate === '' || $endDate === '') {
            return false;
        }
        $row = (new LeaveRequest())->queryOne(
            "SELECT id FROM rateb_leave_requests
             WHERE company_id = :cid AND employee_id = :eid
               AND status IN ('pending', 'approved')
               AND start_date <= :end_d AND end_date >= :start_d
             LIMIT 1",
            [
                'cid' => $companyId,
                'eid' => $employeeId,
                'start_d' => $startDate,
                'end_d' => $endDate,
            ]
        );

        return $row !== null;
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
    private function applyApprovedLeave(array $req, int $requestId = 0): void
    {
        $companyId = (int) ($req['company_id'] ?? 0);
        $employeeId = (int) ($req['employee_id'] ?? 0);
        $start = (string) ($req['start_date'] ?? '');
        $end = (string) ($req['end_date'] ?? '');
        $requestId = $requestId > 0 ? $requestId : (int) ($req['id'] ?? 0);
        if ($companyId < 1 || $employeeId < 1 || $start === '' || $end === '' || $requestId < 1) {
            return;
        }
        $year = (int) date('Y', strtotime($start));
        $this->syncLeaveBalancesForEmployee($companyId, $employeeId, $year);

        $branchId = (int) ($req['branch_id'] ?? 0);
        if ($branchId < 1) {
            $emp = (new Employee())->findByIdUnscoped($employeeId);
            $branchId = (int) ($emp['branch_id'] ?? 0);
        }

        $hasLeaveRequestCol = $this->attendanceHasLeaveRequestIdColumn();
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
                $row = [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'status' => 'leave',
                    'notes' => __('hr_leave_auto_attendance'),
                ];
                if ($branchId > 0) {
                    $row['branch_id'] = $branchId;
                }
                if ($hasLeaveRequestCol) {
                    $row['leave_request_id'] = $requestId;
                }
                $attModel->create($row);
            }
            $cursor = strtotime('+1 day', $cursor);
        }
    }

    /** Delete only attendance rows owned by this leave request (leave_request_id). */
    private function reverseLeaveOwnedAttendance(int $companyId, int $leaveRequestId): int
    {
        if ($companyId < 1 || $leaveRequestId < 1 || !$this->attendanceHasLeaveRequestIdColumn()) {
            return 0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'DELETE FROM rateb_attendance_records
             WHERE company_id = :cid AND leave_request_id = :lid AND status = \'leave\''
        );
        $stmt->execute(['cid' => $companyId, 'lid' => $leaveRequestId]);
        return $stmt->rowCount();
    }

    /**
     * Unpaid leave days overlapping payroll period (calendar-day intersection).
     * Uses paid_snapshot when set; else leave_types.paid. Does not rewrite payroll formula.
     *
     * @return array<int, int> employee_id => day count
     */
    private function batchUnpaidLeaveDaysByEmployee(int $companyId, string $start, string $end): array
    {
        $paidExpr = $this->leaveRequestHasPaidSnapshotColumn()
            ? 'COALESCE(lr.paid_snapshot, lt.paid, 1)'
            : 'COALESCE(lt.paid, 1)';
        $rows = (new LeaveRequest())->query(
            "SELECT lr.employee_id, lr.start_date, lr.end_date,
                    {$paidExpr} AS is_paid
             FROM rateb_leave_requests lr
             JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id AND lt.company_id = lr.company_id
             WHERE lr.company_id = :cid
               AND lr.status = 'approved'
               AND lr.start_date <= :e AND lr.end_date >= :s",
            ['cid' => $companyId, 's' => $start, 'e' => $end]
        );
        $out = [];
        $periodStart = strtotime($start);
        $periodEnd = strtotime($end);
        if ($periodStart === false || $periodEnd === false) {
            return [];
        }
        foreach (is_array($rows) ? $rows : [] as $row) {
            if ((int) ($row['is_paid'] ?? 1) === 1) {
                continue;
            }
            $eid = (int) ($row['employee_id'] ?? 0);
            $ls = strtotime((string) ($row['start_date'] ?? ''));
            $le = strtotime((string) ($row['end_date'] ?? ''));
            if ($eid < 1 || $ls === false || $le === false) {
                continue;
            }
            $from = max($periodStart, $ls);
            $to = min($periodEnd, $le);
            if ($to < $from) {
                continue;
            }
            $days = (int) round(($to - $from) / 86400) + 1;
            $out[$eid] = ($out[$eid] ?? 0) + max(0, $days);
        }
        return $out;
    }

    public function hasPostedPayrollOverlappingLeave(
        int $companyId,
        int $employeeId,
        string $start,
        string $end
    ): bool {
        if ($companyId < 1 || $start === '' || $end === '') {
            return false;
        }
        $periods = (new PayrollPeriod())->query(
            "SELECT id, period_year, period_month FROM rateb_payroll_periods
             WHERE company_id = :cid AND status = 'posted'",
            ['cid' => $companyId]
        );
        $leaveStart = strtotime($start);
        $leaveEnd = strtotime($end);
        if ($leaveStart === false || $leaveEnd === false) {
            return false;
        }
        foreach (is_array($periods) ? $periods : [] as $p) {
            $y = (int) ($p['period_year'] ?? 0);
            $m = (int) ($p['period_month'] ?? 0);
            if ($y < 1 || $m < 1) {
                continue;
            }
            $ps = strtotime(sprintf('%04d-%02d-01', $y, $m));
            $pe = strtotime(date('Y-m-t', $ps ?: time()));
            if ($ps === false || $pe === false) {
                continue;
            }
            if ($leaveStart <= $pe && $leaveEnd >= $ps) {
                // Posted period overlaps leave dates — block cancel/undo (do not mutate posted payroll).
                return true;
            }
        }
        return false;
    }

    private function leaveRequestHasPaidSnapshotColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db = Database::connection();
            $stmt = $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'rateb_leave_requests'
                   AND COLUMN_NAME = 'paid_snapshot'"
            );
            $cached = ((int) ($stmt ? $stmt->fetchColumn() : 0)) > 0;
        } catch (\Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    private function attendanceHasLeaveRequestIdColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db = Database::connection();
            $stmt = $db->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'rateb_attendance_records'
                   AND COLUMN_NAME = 'leave_request_id'"
            );
            $cached = ((int) ($stmt ? $stmt->fetchColumn() : 0)) > 0;
        } catch (\Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    /** @param array<string, mixed> $payload */
    private function auditLeave(string $action, int $leaveRequestId, array $payload = []): void
    {
        try {
            (new AuditService())->log($action, 'hr_leave', $leaveRequestId, $payload);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    private function notifyLeaveOutcome(int $companyId, int $employeeId, int $leaveRequestId, string $outcome): void
    {
        if ($companyId < 1 || $employeeId < 1) {
            return;
        }
        try {
            $emp = (new Employee())->queryOne(
                'SELECT user_id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $employeeId, 'cid' => $companyId]
            );
            $uid = (int) ($emp['user_id'] ?? 0);
            if ($uid < 1) {
                return;
            }
            $titleKey = match ($outcome) {
                'approved' => 'leave_approved',
                'rejected' => 'leave_rejected',
                'cancelled' => 'leave_cancelled',
                default => 'leave_updated',
            };
            $title = function_exists('__') ? (string) __($titleKey) : $titleKey;
            (new NotificationService())->notifyUser(
                $uid,
                $companyId,
                $title !== '' ? $title : $titleKey,
                $title,
                'info',
                'hr_leave_' . $outcome,
                'hr_leave',
                $leaveRequestId
            );
        } catch (\Throwable $e) {
            // best-effort
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
        return $this->payrollStructureTotalsFromRows(is_array($rows) ? $rows : [], $basicSalary);
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
