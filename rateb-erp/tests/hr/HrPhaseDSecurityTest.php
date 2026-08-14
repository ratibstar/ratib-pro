<?php

declare(strict_types=1);

/**
 * Phase D — Payroll correctness & financial state integrity (source analysis).
 *
 * Run: php tests/hr/run-hr-phase-d-tests.php
 */
final class HrPhaseDSecurityTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testAbsenceInputUsesAttendanceAbsentOnly();
        $this->testPeriodBoundariesBetween();
        $this->testBatchAbsenceLoad();
        $this->testSalarySourceIsSalaryBase();
        $this->testEnterpriseSalaryNotInOpsGenerate();
        $this->testFormulaDocumented();
        $this->testDraftToPostedDenied();
        $this->testPostRequiresApproved();
        $this->testPostIdempotentWhenAlreadyPosted();
        $this->testPostDoesNotCallAccounting();
        $this->testPostDoesNotCreateTransfer();
        $this->testAuditFlagsGlAndBankFalse();
        $this->testUiClarifiesPostedNotGl();
        $this->testReconciliationServiceReadOnly();
        $this->testLeaveCreatesLeaveNotAbsent();
        $this->testTenantGuardOnPostApprove();
        $this->testNoPayroll2();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function hrService(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
    }

    private function testAbsenceInputUsesAttendanceAbsentOnly(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'function batchAbsenceDaysByEmployee')
            && str_contains($src, "AND status = 'absent'")
            && str_contains($src, 'Leave days (status=leave) are NOT deducted');
        $this->record('Payroll absence input uses attendance status=absent only', $ok);
    }

    private function testPeriodBoundariesBetween(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, "sprintf('%04d-%02d-01', \$year, \$month)")
            && str_contains($src, "date('Y-m-t', strtotime(\$start))")
            && str_contains($src, 'attendance_date BETWEEN :s AND :e');
        $this->record('Payroll period boundaries are calendar month BETWEEN', $ok);
    }

    private function testBatchAbsenceLoad(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'function batchAbsenceDaysByEmployee')
            && str_contains($src, 'function batchPayrollStructureRows')
            && str_contains($src, 'function batchLoanInstallmentsByEmployee')
            && str_contains($src, 'GROUP BY employee_id');
        $this->record('Attendance/structure/loan inputs batch-loaded (no per-employee COUNT loop)', $ok);
    }

    private function testSalarySourceIsSalaryBase(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'SELECT id, salary_base FROM rateb_employees')
            && str_contains($src, "\$emp['salary_base']");
        $this->record('Ops generate uses rateb_employees.salary_base', $ok);
    }

    private function testEnterpriseSalaryNotInOpsGenerate(): void
    {
        $src = $this->hrService();
        // generatePayrollLines block must not reference enterprise salary table
        $ok = preg_match(
            '/function generatePayrollLines[\s\S]*?function batchAbsenceDaysByEmployee/',
            $src,
            $m
        ) === 1;
        $block = $m[0] ?? '';
        $ok = $ok && !str_contains($block, 'rateb_payroll_employee_salary');
        $this->record('Enterprise salary overlay not used in ops generate', $ok);
    }

    private function testFormulaDocumented(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'net = max(0, salary_base + structure_allowances')
            && str_contains($src, 'Leave days (status=leave) are NOT deducted');
        $this->record('Calculation formula documented on generatePayrollLines', $ok);
    }

    private function testDraftToPostedDenied(): void
    {
        $src = $this->hrService();
        $ok = preg_match(
            '/function postPayroll[\s\S]*?\$status !== \'approved\'[\s\S]*?payroll_not_approved/',
            $src
        ) === 1;
        $this->record('draft → posted remains DENIED', (bool) $ok);
    }

    private function testPostRequiresApproved(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, "if (\$status !== 'approved')")
            && str_contains($src, 'payroll_not_approved');
        $this->record('postPayroll requires approved status', $ok);
    }

    private function testPostIdempotentWhenAlreadyPosted(): void
    {
        $src = $this->hrService();
        $ok = preg_match(
            '/function postPayroll[\s\S]*?\$status === \'posted\'[\s\S]*?return;/',
            $src
        ) === 1;
        $this->record('postPayroll is idempotent when already posted', (bool) $ok);
    }

    private function testPostDoesNotCallAccounting(): void
    {
        $src = $this->hrService();
        $ok = preg_match('/function postPayroll\s*\((.*?)(?=\n    public function |\n    private function loadPayrollPeriod)/s', $src, $m) === 1;
        $block = $m[0] ?? '';
        $ok = $ok && !str_contains($block, 'AccountingService') && !str_contains($block, 'createJournal');
        $this->record('postPayroll does not call AccountingService / create journal', $ok);
    }

    private function testPostDoesNotCreateTransfer(): void
    {
        $src = $this->hrService();
        $ok = preg_match('/function postPayroll\s*\((.*?)(?=\n    public function |\n    private function loadPayrollPeriod)/s', $src, $m) === 1;
        $block = $m[0] ?? '';
        // Audit may flag bank_transfer=false; must not instantiate transfer services or tables.
        $ok = $ok
            && !str_contains($block, 'InterBranchTransfer')
            && !str_contains($block, 'PayrollTransfer')
            && !str_contains($block, 'bank_transfers')
            && !preg_match('/new\s+\w*Transfer/', $block);
        $this->record('postPayroll does not create bank transfer', $ok);
    }

    private function testAuditFlagsGlAndBankFalse(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, "'gl_posted' => false")
            && str_contains($src, "'bank_transfer' => false")
            && str_contains($src, 'payroll_status_lock_only');
        $this->record('Post audit payload marks gl_posted/bank_transfer false', $ok);
    }

    private function testUiClarifiesPostedNotGl(): void
    {
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $view = (string) file_get_contents(RATEB_ROOT . '/views/company/hr/payroll/show.php');
        $ok = str_contains($en, 'payroll_posted_status_note')
            && str_contains($ar, 'payroll_posted_status_note')
            && str_contains($en, 'No GL journal')
            && str_contains($view, 'payroll_posted_status_note')
            && str_contains($en, 'Lock payroll (status only)');
        $this->record('UI/lang clarifies posted ≠ GL ≠ bank transfer', $ok);
    }

    private function testReconciliationServiceReadOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollIntegrityService.php');
        $ok = str_contains($src, 'function diagnosePeriod')
            && str_contains($src, 'Does NOT mutate')
            && str_contains($src, 'none_expected')
            && !str_contains($src, '->update(')
            && !str_contains($src, '->create(')
            && !str_contains($src, '->delete(');
        $this->record('Payroll reconciliation diagnostic is read-only', $ok);
    }

    private function testLeaveCreatesLeaveNotAbsent(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'function applyApprovedLeave')
            && str_contains($src, "'status' => 'leave'");
        $this->record('Approved leave writes attendance status=leave (not absent)', $ok);
    }

    private function testTenantGuardOnPostApprove(): void
    {
        $src = $this->hrService();
        $ok = str_contains($src, 'function loadPayrollPeriodForMutation')
            && str_contains($src, 'periodCompanyId !== $cid')
            && str_contains($src, 'approvePayroll(int $periodId, ?int $expectedCompanyId')
            && str_contains($src, 'postPayroll(int $periodId, ?int $expectedCompanyId');
        $this->record('Approve/post still enforce company tenant guard', $ok);
    }

    private function testNoPayroll2(): void
    {
        $src = $this->hrService();
        $ok = !str_contains($src, 'PayrollEngine2')
            && !str_contains($src, 'rateb_payroll_periods_v2')
            && !str_contains($src, 'generatePayrollLinesV2');
        $this->record('No Payroll2 / parallel engine introduced', $ok);
    }
}
