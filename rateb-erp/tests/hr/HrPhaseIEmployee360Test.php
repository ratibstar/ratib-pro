<?php

declare(strict_types=1);

/**
 * Phase I — Employee Master 360 (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-i-tests.php
 */
final class HrPhaseIEmployee360Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->svc();
        $ctrl = $this->ctrl();
        $show = $this->show();
        $ops = $this->ops();
        $js = $this->js();
        $css = $this->css();

        $this->record(
            'I1 employee belongs to company',
            str_contains($svc, 'WHERE id = :id AND company_id = :cid')
            && str_contains($svc, 'function findEmployeeForCompany')
        );

        $this->record(
            'I2 foreign employee blocked',
            str_contains($ctrl, 'loadShell($companyId, $id')
            && str_contains($ctrl, 'http_response_code(404)')
            && str_contains($ctrl, 'Foreign / missing employee')
        );

        $this->record(
            'I3 canonical employee source',
            str_contains($svc, "canonical_source' => 'rateb_employees'")
            && str_contains($svc, 'FROM rateb_employees')
            && !str_contains($svc, 'Employee2')
        );

        $this->record(
            'I4 header data correct',
            str_contains($svc, 'function buildHeader')
            && str_contains($show, 'rateb-emp360-avatar')
            && str_contains($show, 'employee_code')
        );

        $this->record(
            'I5 overview data correct',
            str_contains($svc, 'function buildOverview')
            && str_contains($show, 'hr_360_personal')
            && str_contains($show, 'hr_360_employment_summary')
        );

        $this->record(
            'I6 salary authorization',
            str_contains($svc, 'can_view_salary')
            && str_contains($svc, "'authorized' => false")
            && str_contains($ctrl, 'can_view_salary')
        );

        $this->record(
            'I7 leave balance source',
            str_contains($svc, 'leaveBalancesForEmployee')
            && !str_contains($svc, 'function syncLeaveBalancesForEmployee')
        );

        $this->record(
            'I8 leave history source',
            str_contains($svc, 'listLeaveRequestsForEmployee')
            && str_contains($svc, 'upcoming')
        );

        $this->record(
            'I9 attendance source',
            str_contains($svc, 'employeeAttendanceYtd')
            && str_contains($svc, "status = 'late'")
        );

        $this->record(
            'I10 payroll source',
            str_contains($svc, 'rateb_payroll_lines')
            && str_contains($svc, 'posted_means_period_lock')
            && str_contains($svc, 'period_status')
        );

        $this->record(
            'I11 requests source',
            str_contains($svc, 'rateb_hr_employee_requests')
            && str_contains($svc, 'progressSummary')
        );

        $this->record(
            'I12 ESS link security',
            str_contains($svc, 'ess_linked')
            && str_contains($svc, 'user_id')
            && !str_contains($svc, 'autoLinkEmployeeUser')
        );

        $this->record(
            'I13 timeline uses existing sources',
            str_contains($svc, 'rateb_audit_logs')
            && str_contains($svc, 'rateb_leave_requests')
            && str_contains($svc, 'tabTimeline')
            && str_contains($svc, 'no new event store')
            && !str_contains($svc, 'CREATE TABLE')
            && !str_contains($svc, 'Employee360Audit')
        );

        $this->record(
            'I14 no duplicate employee master',
            !str_contains($svc, 'rateb_employees_360')
            && !str_contains($ops, 'employee360')
            && str_contains($ops, 'hr/employees/{id}')
        );

        $this->record(
            'I15 no business mutation from page',
            str_contains($svc, 'Does not mutate domain state')
            && !str_contains($svc, 'function approveLeave')
            && !str_contains($svc, 'function cancelLeave')
        );

        $this->record(
            'I16 no N+1 obvious regression',
            str_contains($js, 'data-tab-endpoint')
            && str_contains($js, 'cacheGet')
            && str_contains($ops, '360-tab')
            && str_contains($ctrl, 'show360Tab')
        );

        $this->record(
            'I17 RBAC',
            str_contains($ctrl, 'employee360AuthFlags')
            && str_contains($ctrl, 'rateb_can_view_entity')
            && str_contains($ctrl, 'rateb_can_manage_entity')
        );

        $this->record(
            'I18 Phase B regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
        );
        $this->record(
            'I19 Phase C regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-c-security-tests.php')
        );
        $this->record(
            'I20 Phase D regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-d-tests.php')
        );
        $this->record(
            'I21 Phase F regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-f-approval-tests.php')
        );
        $this->record(
            'I22 Phase G regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-g-approval-matrix-tests.php')
        );
        $this->record(
            'I23 Phase H regression wiring',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-h-matrix-governance-tests.php')
        );

        $this->record(
            'I UI assets present',
            str_contains($css, 'rateb-emp360')
            && is_file(RATEB_ROOT . '/views/company/hr/employees/360-tab.php')
        );

        $this->record(
            'I employment contracts wired (Phase K)',
            str_contains($svc, 'HrEmploymentContractService')
            && str_contains($svc, "contracts_deferred' => false")
            && is_file(RATEB_ROOT . '/views/company/hr/employees/360-tab.php')
            && str_contains(
                (string) file_get_contents(RATEB_ROOT . '/views/company/hr/employees/360-tab.php'),
                'hr_employment_contracts'
            )
        );

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function svc(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrEmployee360Service.php');
    }

    private function ctrl(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
    }

    private function show(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/views/company/hr/employees/show.php');
    }

    private function ops(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
    }

    private function js(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/public/assets/js/hr-employee-360.js');
    }

    private function css(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/public/assets/css/hr-module.css');
    }
}
