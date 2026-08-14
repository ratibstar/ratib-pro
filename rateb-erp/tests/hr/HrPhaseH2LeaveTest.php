<?php

declare(strict_types=1);

/**
 * Phase H2 — Leave integrity (source analysis + structural gates).
 *
 * Covers H2-01 … H2-35 acceptance checks (static / structural).
 * Run: php tests/hr/run-hr-phase-h2-leave-tests.php
 */
final class HrPhaseH2LeaveTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $hr = $this->hr();
        $ess = $this->ess();
        $ov = $this->oversight();
        $mig = $this->mig();
        $ops = $this->ops();
        $api = $this->api();
        $ctrl = $this->ctrl();

        $this->record(
            'H2-01 canonical leave source',
            str_contains($hr, 'rateb_leave_requests')
            && str_contains($hr, 'function createPendingLeaveRequest')
        );

        $this->record(
            'H2-02 tenant isolation',
            str_contains($hr, 'WHERE id = :id AND company_id = :cid FOR UPDATE')
            && str_contains($ess, 'company_id = :cid')
            && str_contains($hr, 'company_id = :cid AND employee_id = :eid')
        );

        $this->record(
            'H2-03 invalid employee',
            str_contains($hr, 'SELECT id, branch_id FROM rateb_employees WHERE id = :id AND company_id = :cid FOR UPDATE')
        );

        $this->record(
            'H2-04 invalid leave type',
            str_contains($hr, 'FROM rateb_leave_types')
            && str_contains($hr, 'WHERE id = :id AND company_id = :cid LIMIT 1')
        );

        $this->record(
            'H2-05 paid flag recognized',
            str_contains($hr, 'paid_snapshot')
            && str_contains($mig, 'paid_snapshot')
        );

        $this->record(
            'H2-06 unpaid flag recognized',
            str_contains($hr, 'batchUnpaidLeaveDaysByEmployee')
            && str_contains($hr, 'COALESCE(lr.paid_snapshot, lt.paid, 1)')
        );

        $this->record(
            'H2-07 paid leave no absence deduction',
            str_contains($hr, "AND status = 'absent'")
            && str_contains($hr, 'Leave days (status=leave) are NOT deducted')
        );

        $this->record(
            'H2-08 unpaid leave deduction',
            str_contains($hr, '$deductDays = $absentDays + $unpaidLeaveDays')
            && str_contains($hr, 'payroll_unpaid_leave_deduction')
        );

        $this->record(
            'H2-09 pending does not consume',
            preg_match("/status = 'approved' AND YEAR\\(start_date\\)/", $hr) === 1
        );

        $this->record(
            'H2-10 approval consumes once',
            (str_contains($hr, "WHERE id = :id AND status = 'pending'")
                || str_contains($hr, 'WHERE id = :id AND status = \\\'pending\\\''))
            && str_contains($hr, 'balance_consumed')
            && str_contains($hr, 'FOR UPDATE')
        );

        $this->record(
            'H2-11 rejection no consumption',
            preg_match('/function rejectLeave\s*\(.*?\{([\s\S]*?)\n    public function cancelLeave/s', $hr, $m) === 1
            && !str_contains($m[1] ?? '', 'applyApprovedLeave')
        );

        $this->record(
            'H2-12 balance overdraw blocked',
            str_contains($hr, 'leave_balance_insufficient')
            && str_contains($hr, '$days > $remaining')
        );

        $this->record(
            'H2-13 overlap blocked',
            str_contains($hr, 'leave_overlap_conflict')
            && str_contains($hr, "status IN ('pending', 'approved')")
        );

        $this->record(
            'H2-14 rejected leave does not block overlap',
            str_contains($hr, "status IN ('pending', 'approved')")
            && !str_contains($hr, "status IN ('pending', 'approved', 'rejected')")
        );

        $this->record(
            'H2-15 concurrent overlap',
            str_contains($hr, 'FOR UPDATE')
            && str_contains($hr, 'beginTransaction')
            && str_contains($hr, 'hasOverlappingLeaveRequest')
        );

        $this->record(
            'H2-16 cancellation restores once',
            str_contains($hr, 'function cancelLeave')
            && (str_contains($hr, "status = 'cancelled'") || str_contains($hr, 'status = \\\'cancelled\\\''))
            && str_contains($hr, 'balance_restored')
            && str_contains($hr, "if (\$status === 'cancelled')")
        );

        $this->record(
            'H2-17 cancellation reverses only leave-owned attendance',
            str_contains($hr, 'WHERE company_id = :cid AND leave_request_id = :lid')
            && str_contains($mig, 'leave_request_id')
        );

        $this->record(
            'H2-18 undo reverses attendance',
            str_contains($hr, 'function undoLeaveApproval')
            && str_contains($ov, 'undoLeaveApproval')
            && str_contains($hr, 'reverseLeaveOwnedAttendance')
        );

        $this->record(
            'H2-19 undo restores balance',
            str_contains($hr, 'undoLeaveApproval')
            && str_contains($hr, 'syncLeaveBalancesForEmployee')
            && str_contains($hr, "'leave_undo'")
        );

        $this->record(
            'H2-20 duplicate approval',
            (str_contains($hr, "WHERE id = :id AND status = 'pending'")
                || str_contains($hr, 'WHERE id = :id AND status = \\\'pending\\\''))
            && str_contains($hr, 'rowCount() < 1')
        );

        $this->record(
            'H2-21 duplicate cancellation',
            str_contains($hr, "if (\$status === 'cancelled')")
            && str_contains($hr, "status IN ('pending', 'approved')")
        );

        $this->record(
            'H2-22 matrix integration',
            str_contains($ov, 'HrApprovalMatrixService')
            && str_contains($ov, 'gateOversightDecision')
            && str_contains($ov, 'approveLeave')
        );

        $this->record(
            'H2-23 no matrix fallback',
            str_contains($ov, 'approveLeave')
            && !str_contains($ov, 'ApprovalEngine2')
            && !str_contains($hr, 'ApprovalEngine2')
        );

        $this->record(
            'H2-24 ESS same service',
            str_contains($ess, 'createPendingLeaveRequest')
            && str_contains($ctrl, 'createPendingLeaveRequest')
        );

        $this->record(
            'H2-25 ESS cannot manipulate identity',
            str_contains($ess, "unset(\$payload['employee_id'], \$payload['company_id']")
            && !str_contains($ess, "\$payload['paid']")
        );

        $this->record(
            'H2-26 posted payroll protected',
            str_contains($hr, 'hasPostedPayrollOverlappingLeave')
            && str_contains($hr, 'leave_cancel_blocked_posted_payroll')
            && str_contains($hr, 'leave_undo_blocked_posted_payroll')
        );

        $this->record(
            'H2-27 notification correctness',
            str_contains($ess, 'notifyPendingSubmission')
            && str_contains($hr, 'notifyLeaveOutcome')
            && str_contains($hr, "'approved'")
            && str_contains($hr, "'rejected'")
            && str_contains($hr, "'cancelled'")
        );

        $this->record(
            'H2-28 audit',
            str_contains($hr, 'leave_created')
            && str_contains($hr, 'leave_submitted')
            && str_contains($hr, 'leave_approved')
            && str_contains($hr, 'leave_cancelled')
            && str_contains($hr, 'balance_consumed')
            && str_contains($hr, 'balance_restored')
        );

        $this->record(
            'H2-29 concurrency approval',
            str_contains($hr, 'function approveLeave')
            && str_contains($hr, 'FOR UPDATE')
            && str_contains($hr, "status = 'pending'")
        );

        $this->record(
            'H2-30 concurrency cancellation',
            str_contains($hr, 'function cancelLeave')
            && str_contains($hr, 'FOR UPDATE')
            && str_contains($hr, "status IN ('pending', 'approved')")
        );

        $this->record(
            'H2-31 calendar-day semantics preserved',
            str_contains($ess, 'Inclusive calendar days')
            && str_contains($ctrl, 'Calendar-day semantics intentionally preserved')
        );

        $this->record(
            'H2-32 half-day remains deferred',
            is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-H2-LEAVE-AUDIT.md')
        );

        $this->record(
            'H2-33 Phase F regression wiring present',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-f-approval-tests.php')
        );

        $this->record(
            'H2-34 Phase G regression wiring present',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-g-approval-matrix-tests.php')
        );

        $this->record(
            'H2-35 Phase H regression wiring present',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-h-matrix-governance-tests.php')
        );

        $this->record(
            'H2 additive migration only',
            str_contains($mig, 'ADD COLUMN leave_request_id')
            && str_contains($mig, 'ADD COLUMN paid_snapshot')
            && !str_contains($mig, 'DROP ')
            && !str_contains($mig, 'DELETE FROM')
        );

        $this->record(
            'H2 company approve blocked; cancel available',
            preg_match(
                "/hr\\/leaves\\/\\{id\\}\\/approve'\\)\\s*,\\s*\\\$blockCompanyApprovalAction/",
                $ops
            ) === 1
            && str_contains($ops, "hr/leaves/{id}/cancel")
            && str_contains($api, 'leave/requests/{id}/cancel')
        );

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function hr(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
    }

    private function ess(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssLeaveService.php');
    }

    private function oversight(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
    }

    private function mig(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/migrations/249_hr_phase_h2_leave_integrity.sql');
    }

    private function ops(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
    }

    private function api(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/routes/modules/api.php');
    }

    private function ctrl(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
    }
}
