<?php

declare(strict_types=1);

/**
 * Phase K — HireBridge + Employment Contracts (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-k-tests.php
 */
final class HrPhaseKContractsTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $hire = $this->file('/app/services/HrHireBridgeService.php');
        $contract = $this->file('/app/services/HrEmploymentContractService.php');
        $wf = $this->file('/app/services/RecruitmentWorkflowService.php');
        $mig = $this->file('/migrations/250_hr_phase_k_hirebridge_contracts.sql');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $cron = $this->file('/app/services/CronService.php');
        $svc360 = $this->file('/app/services/HrEmployee360Service.php');
        $tab360 = $this->file('/views/company/hr/employees/360-tab.php');
        $ctrl = $this->file('/app/controllers/Company/HrControllers.php');
        $model = $this->file('/app/models/HrModels.php');

        $this->record(
            'K0 additive migration HireBridge + employment contracts',
            str_contains($mig, 'rateb_hr_employment_contracts')
            && str_contains($mig, 'recruitment_candidate_id')
            && !preg_match('/\bDROP\b/i', $mig)
            && str_contains($mig, 'CREATE TABLE IF NOT EXISTS')
        );

        $this->record(
            'K1 HireBridge on ready→deployed creates/links rateb_employees',
            str_contains($wf, 'HrHireBridgeService')
            && str_contains($wf, 'STATUS_DEPLOYED')
            && str_contains($hire, 'hireFromCandidate')
            && str_contains($hire, 'rateb_employees')
            && !preg_match('/\bclass\s+Employee2\b/', $hire)
        );

        $this->record(
            'K2 duplicate employee blocked (candidate id + national_id link)',
            str_contains($hire, 'findEmployeeByCandidate')
            && str_contains($hire, 'findEmployeeByNationalId')
            && str_contains($hire, 'linkCandidateToEmployee')
            && str_contains($mig, 'uq_emp_recruitment_candidate')
        );

        $this->record(
            'K3 employment contract entity distinct from commercial contracts',
            str_contains($contract, 'rateb_hr_employment_contracts')
            && str_contains($model, 'class HrEmploymentContract')
            && !str_contains($contract, 'FROM rateb_contracts')
            && !str_contains($contract, 'rateb_eproc_contracts')
            && str_contains($ctrl, 'HrEmploymentContractsController')
        );

        $this->record(
            'K4 contract CRUD fields employee_id/contract_no/dates/salary/status',
            str_contains($contract, 'function create')
            && str_contains($contract, 'function update')
            && str_contains($contract, "'employee_id'")
            && str_contains($contract, 'contract_no')
            && str_contains($contract, 'start_date')
            && str_contains($contract, 'end_date')
            && str_contains($contract, 'salary')
        );

        $this->record(
            'K5 lifecycle draft→active→expired/terminated',
            str_contains($contract, "STATUS_DRAFT = 'draft'")
            && str_contains($contract, "STATUS_ACTIVE = 'active'")
            && str_contains($contract, "STATUS_EXPIRED = 'expired'")
            && str_contains($contract, "STATUS_TERMINATED = 'terminated'")
            && str_contains($contract, 'function activate')
            && str_contains($contract, 'function terminate')
            && str_contains($contract, 'processExpiryStatus')
        );

        $this->record(
            'K6 expiry alerts via cron (not commercial trigger)',
            str_contains($contract, 'processExpiryAlerts')
            && str_contains($contract, 'hr_employment_contract_expiry')
            && str_contains($cron, 'hr_employment_contract_alerts')
            && !str_contains($contract, 'triggerContractExpiry')
        );

        $this->record(
            'K7 tenant isolation on contract + hire queries',
            str_contains($contract, 'company_id = :cid')
            && str_contains($contract, 'assertEmployeeBelongs')
            && str_contains($hire, 'company_id = :cid')
            && str_contains($hire, 'assertCandidate')
        );

        $this->record(
            'K8 RBAC routes under hr-employees entity middleware',
            str_contains($ops, 'hr/employment-contracts')
            && str_contains($ops, 'HrEmploymentContractsController')
            && str_contains($ops, "rateb_erp_mw('hr', '', 'hr-employees')")
            && str_contains($menu, 'hr/employment-contracts')
        );

        $this->record(
            'K9 audit on create/update/activate/terminate + hirebridge',
            str_contains($contract, 'hr_employment_contract_create')
            && str_contains($contract, 'hr_employment_contract_update')
            && str_contains($contract, 'hr_employment_contract_activate')
            && str_contains($contract, 'hr_employment_contract_terminate')
            && str_contains($hire, 'hirebridge_create')
            && str_contains($hire, 'hirebridge_link')
        );

        $this->record(
            'K10 Employee 360 employment tab shows contracts',
            str_contains($svc360, 'HrEmploymentContractService')
            && str_contains($svc360, "contracts_deferred' => false")
            && str_contains($tab360, 'hr_employment_contracts')
            && !str_contains($tab360, 'hr_360_contracts_deferred')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-j-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-i-tests.php')
        );

        $this->record(
            'K boundaries: no payroll/approval/letter PDF rewrite',
            !str_contains($contract, 'approvePayroll')
            && !str_contains($hire, 'ApprovalEngine')
            && !str_contains($contract, 'letter pdf')
            && !str_contains($hire, 'Payroll2')
        );

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function file(string $rel): string
    {
        $path = RATEB_ROOT . $rel;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
