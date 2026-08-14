<?php

declare(strict_types=1);

/**
 * Phase C — Employee Master integrity + salary change governance (source analysis).
 *
 * Run: php tests/hr/run-hr-phase-c-security-tests.php
 */
final class HrPhaseCSecurityTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testCanonicalEmployeeTable();
        $this->testNoEmployee2Tables();
        $this->testEmployeeModelTenantScoped();
        $this->testBindEmployeeUserStillCompanyScoped();
        $this->testAutoLinkStillCompanyScoped();
        $this->testLegacyEmployeeAssertSameCompany();
        $this->testHrmsCreateUsesLegacyAssert();
        $this->testIntegrityDiagnosticExists();
        $this->testIntegritySqlCompanyScoped();
        $this->testSalaryChangeAuditOpsWired();
        $this->testSalaryCreatedAuditOpsWired();
        $this->testSalaryAuditCapturesOldNewEffective();
        $this->testEnterpriseSalaryAuditWired();
        $this->testCrudHooksPresent();
        $this->testPayrollWorkflowUnchanged();
        $this->testOpsPayrollStillUsesSalaryBase();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testCanonicalEmployeeTable(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/models/HrModels.php');
        $ok = str_contains($src, 'Canonical live Employee Master')
            && str_contains($src, "protected string \$table = 'rateb_employees'");
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = $ok && str_contains($svc, "CANONICAL_TABLE = 'rateb_employees'");
        $this->record('Canonical employee identity is rateb_employees', $ok);
    }

    private function testNoEmployee2Tables(): void
    {
        $forbidden = [
            'rateb_hr_employees',
            'rateb_hr_employees_v2',
            'hr_employee_master',
            'employee_master_v2',
        ];
        $paths = [
            RATEB_ROOT . '/app/models/HrModels.php',
            RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php',
            RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php',
        ];
        $ok = true;
        foreach ($paths as $p) {
            $src = (string) file_get_contents($p);
            foreach ($forbidden as $t) {
                if (str_contains($src, $t)) {
                    $ok = false;
                }
            }
        }
        $this->record('No Employee2 / parallel master tables introduced', $ok);
    }

    private function testEmployeeModelTenantScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/models/HrModels.php');
        $ok = preg_match(
            '/final class Employee[\s\S]*?tenantScoped\s*=\s*true/',
            $src
        ) === 1;
        $this->record('Employee model is tenantScoped (company boundary)', (bool) $ok);
    }

    private function testBindEmployeeUserStillCompanyScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEssEmployeeResolverService.php');
        $ok = str_contains($src, 'function bindEmployeeUser(int $employeeId, int $userId, int $companyId)')
            && str_contains($src, 'AND company_id = :cid')
            && str_contains($src, 'companyId < 1');
        $this->record('bindEmployeeUser still requires company_id (Phase B regression)', $ok);
    }

    private function testAutoLinkStillCompanyScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $ok = str_contains($src, 'company-scoped bind only')
            && str_contains($src, 'LOWER(TRIM(email)) = :em AND company_id = :cid');
        $this->record('autoLinkEmployeeUser still company-scoped (Phase B regression)', $ok);
    }

    private function testLegacyEmployeeAssertSameCompany(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesSupport.php');
        $ok = str_contains($src, 'function assertLegacyEmployee')
            && str_contains($src, 'legacy_employee_tenant_mismatch')
            && str_contains($src, 'WHERE id = :id AND company_id = :cid');
        $this->record('legacy_employee_id link requires same company', $ok);
    }

    private function testHrmsCreateUsesLegacyAssert(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HumanResourcesDomainServices.php');
        $ok = substr_count($src, 'assertLegacyEmployee') >= 2;
        $this->record('HRMS profile create/update asserts legacy employee company', $ok);
    }

    private function testIntegrityDiagnosticExists(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = str_contains($src, 'function diagnoseCompany(int $companyId)')
            && str_contains($src, 'Never mutates data')
            && str_contains($src, 'No automatic merge');
        $this->record('Duplicate/orphan diagnostic is read-only', $ok);
    }

    private function testIntegritySqlCompanyScoped(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = str_contains($src, 'WHERE company_id = :cid')
            && str_contains($src, 'attendance_missing_employee')
            && str_contains($src, 'leave_cross_company_employee')
            && str_contains($src, 'profiles_orphan_legacy');
        $this->record('Integrity SQL covers payroll/attendance/leave/HRMS orphans', $ok);
    }

    private function testSalaryChangeAuditOpsWired(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = str_contains($ctrl, 'maybeAuditOpsSalaryChange')
            && str_contains($svc, "log('salary_changed', 'hr_employees'")
            && str_contains($svc, 'old_salary_base')
            && str_contains($svc, 'new_salary_base');
        $this->record('Ops salary change produces dedicated audit', $ok);
    }

    private function testSalaryCreatedAuditOpsWired(): void
    {
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = str_contains($ctrl, 'maybeAuditOpsSalaryCreated')
            && str_contains($svc, "log('salary_created', 'hr_employees'");
        $this->record('Ops salary create produces dedicated audit', $ok);
    }

    private function testSalaryAuditCapturesOldNewEffective(): void
    {
        $svc = (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployeeIntegrityService.php');
        $ok = str_contains($svc, "'old_salary_base'")
            && str_contains($svc, "'new_salary_base'")
            && str_contains($svc, "'effective_date'")
            && str_contains($svc, "'company_id'");
        $this->record('Salary audit captures old/new/effective_date/company', $ok);
    }

    private function testEnterpriseSalaryAuditWired(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/PayrollDomainServices.php');
        $ok = str_contains($src, 'maybeAuditEnterpriseSalaryChange')
            && str_contains($src, 'assertLegacyEmployee');
        $this->record('Enterprise salary update audited + legacy link tenant-checked', $ok);
    }

    private function testCrudHooksPresent(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/Controllers/CrudController.php');
        $ok = str_contains($src, 'function afterSuccessfulStore')
            && str_contains($src, 'function afterSuccessfulUpdate')
            && str_contains($src, '$this->afterSuccessfulUpdate(')
            && str_contains($src, '$this->afterSuccessfulStore(');
        $this->record('CrudController exposes afterSuccessfulStore/Update hooks', $ok);
    }

    private function testPayrollWorkflowUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match(
            '/function postPayroll[\s\S]*?\$status !== \'approved\'[\s\S]*?payroll_not_approved/',
            $src
        ) === 1;
        $ok = $ok && preg_match(
            '/function approvePayroll[\s\S]*?!== \'draft\'[\s\S]*?payroll_not_draft/',
            $src
        ) === 1;
        $this->record('Payroll workflow draft→approved→posted unchanged', (bool) $ok);
    }

    private function testOpsPayrollStillUsesSalaryBase(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = str_contains($src, 'SELECT id, salary_base FROM rateb_employees')
            && str_contains($src, "\$emp['salary_base']");
        $this->record('Ops payroll still reads rateb_employees.salary_base', $ok);
    }
}
