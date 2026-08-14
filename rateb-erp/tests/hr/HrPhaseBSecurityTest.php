<?php

declare(strict_types=1);

/**
 * Phase B — ESS tenant isolation, payroll post/approve integrity, leave notify, audit wiring.
 *
 * Run: php tests/hr/run-hr-phase-b-security-tests.php
 */
final class HrPhaseBSecurityTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testResolverRequiresCompany();
        $this->testResolverSqlAlwaysCompanyScoped();
        $this->testBindRequiresCompanyPredicate();
        $this->testNoGlobalEmailFallbackInResolver();
        $this->testAutoLinkNoGlobalFallback();
        $this->testPostRequiresApprovedStatus();
        $this->testApproveRequiresDraftStatus();
        $this->testPayrollMutationTenantGuard();
        $this->testPayrollAuditWired();
        $this->testLeaveApplyNotifiesOversight();
        $this->testLeaveNotifyAfterPersistOnly();
        $this->testPayrollQueriesCompanyScoped();
        $this->testMigrationIndexPresent();
        $this->testNoPayrollAuditMutatingRoutes();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testResolverRequiresCompany(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php');
        $ok = str_contains($src, "code' => 'company_required'")
            && str_contains($src, "code' => 'tenant_mismatch'")
            && str_contains($src, 'companyId < 1');
        $this->record('ESS resolver requires company and rejects tenant mismatch', $ok);
    }

    private function testResolverSqlAlwaysCompanyScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php');
        $ok = str_contains($src, 'WHERE user_id = :uid AND company_id = :cid')
            && str_contains($src, 'WHERE LOWER(TRIM(email)) = :em AND company_id = :cid')
            && !str_contains($src, 'Token company may be wrong')
            && !preg_match('/WHERE user_id = :uid\s*\n\s*ORDER BY/', $src);
        $this->record('ESS resolver SQL always includes company_id', $ok);
    }

    private function testBindRequiresCompanyPredicate(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php');
        $ok = str_contains($src, 'function bindEmployeeUser(int $employeeId, int $userId, int $companyId)')
            && str_contains($src, 'AND company_id = :cid')
            && str_contains($src, 'companyId < 1')
            && str_contains($src, 'return false');
        $this->record('bindEmployeeUser requires company_id and denies cross-tenant', $ok);
    }

    private function testNoGlobalEmailFallbackInResolver(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php');
        $ok = !str_contains($src, 'Match email globally')
            && !str_contains($src, 'Token company may be wrong')
            && str_contains($src, 'WHERE user_id = :uid AND company_id = :cid')
            && str_contains($src, 'WHERE LOWER(TRIM(email)) = :em AND company_id = :cid');
        $this->record('No cross-tenant email/user fallback in ESS resolver', $ok);
    }

    private function testAutoLinkNoGlobalFallback(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $ok = str_contains($src, 'company-scoped bind only')
            && str_contains($src, 'LOWER(TRIM(email)) = :em AND company_id = :cid')
            && !str_contains($src, "ORDER BY id ASC LIMIT 1',\n                ['em' => \$email]");
        $this->record('Admin autoLinkEmployeeUser is company-scoped only', $ok);
    }

    private function testPostRequiresApprovedStatus(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match(
            '/function postPayroll[\s\S]*?\$status !== \'approved\'[\s\S]*?payroll_not_approved/',
            $src
        ) === 1;
        $this->record('postPayroll rejects non-approved periods (no draft bypass)', (bool) $ok);
    }

    private function testApproveRequiresDraftStatus(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match(
            '/function approvePayroll[\s\S]*?!== \'draft\'[\s\S]*?payroll_not_draft/',
            $src
        ) === 1;
        $this->record('approvePayroll requires draft status', (bool) $ok);
    }

    private function testPayrollMutationTenantGuard(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $oversight = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
        $ok = str_contains($src, 'function loadPayrollPeriodForMutation')
            && str_contains($src, 'periodCompanyId !== $cid')
            && str_contains($ctrl, 'postPayroll($id, $companyId > 0 ? $companyId : null)')
            && str_contains($oversight, 'approvePayroll($recordId, $companyId > 0 ? $companyId : null)');
        $this->record('Payroll approve/post enforce company isolation', $ok);
    }

    private function testPayrollAuditWired(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'function recordPayrollAudit')
            && str_contains($src, 'PayrollAudit')
            && str_contains($src, "entity_type' => 'hr_payroll_period'")
            && str_contains($src, "recordPayrollAudit('approved'")
            && str_contains($src, "recordPayrollAudit('posted'")
            && str_contains($src, "recordPayrollAudit('calculated'");
        $this->record('Ops payroll writes rateb_payroll_audit + AuditService', $ok);
    }

    private function testLeaveApplyNotifiesOversight(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssLeaveService.php');
        $createNeedle = str_contains($src, 'createPendingLeaveRequest')
            ? 'createPendingLeaveRequest'
            : 'LeaveRequest())->create';
        $ok = str_contains($src, 'ApprovalOversightService::notifyPendingSubmission')
            && str_contains($src, "'hr_leave'")
            && str_contains($src, $createNeedle)
            && strpos($src, $createNeedle) < strpos($src, 'notifyPendingSubmission');
        $this->record('ESS leave apply notifies oversight after create', $ok);
    }

    private function testLeaveNotifyAfterPersistOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssLeaveService.php');
        $createPos = strpos($src, 'createPendingLeaveRequest');
        if ($createPos === false) {
            $createPos = strpos($src, '(new LeaveRequest())->create($create)');
        }
        $notifyPos = strpos($src, 'notifyPendingSubmission');
        $ok = $createPos !== false && $notifyPos !== false && $createPos < $notifyPos
            && str_contains($src, 'if ($id > 0)');
        $this->record('Leave notification only after successful persist', $ok);
    }

    private function testPayrollQueriesCompanyScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $ok = substr_count($src, 'pl.company_id = :cid') >= 2
            && str_contains($src, 'e.company_id = pl.company_id');
        $this->record('Payroll show/export/payslip queries are company-scoped', $ok);
    }

    private function testMigrationIndexPresent(): void
    {
        $path = RATEB_ROOT . '/migrations/247_hr_phase_b_ess_user_company_index.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($sql, 'idx_employees_user_company')
            && str_contains($sql, '(user_id, company_id)')
            && str_contains($sql, 'CREATE') === false; // ALTER additive only
        $ok = $ok && str_contains($sql, 'ALTER TABLE rateb_employees ADD INDEX');
        $this->record('Additive migration for employees user+company index', $ok);
    }

    private function testNoPayrollAuditMutatingRoutes(): void
    {
        $ops = (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
        $ok = !str_contains($ops, 'payroll_audit')
            && !str_contains($ops, 'PayrollAuditController')
            && !str_contains($ops, 'rateb_payroll_audit');
        $this->record('No app routes expose payroll audit mutation', $ok);
    }
}
