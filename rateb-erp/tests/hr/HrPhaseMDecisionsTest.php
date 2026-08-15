<?php

declare(strict_types=1);

/**
 * Phase M — Decisions + Disciplinary (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-m-tests.php
 */
final class HrPhaseMDecisionsTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->file('/app/services/HrDecisionService.php');
        $disc = $this->file('/app/services/HrDisciplinaryService.php');
        $mig = $this->file('/migrations/252_hr_phase_m_decisions.sql');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $oversight = $this->file('/app/services/ApprovalOversightService.php');
        $matrix = $this->file('/app/services/HrApprovalMatrixService.php');
        $inbox = $this->file('/app/services/HrApprovalInboxService.php');
        $svc360 = $this->file('/app/services/HrEmployee360Service.php');
        $tab360 = $this->file('/views/company/hr/employees/360-tab.php');
        $show360 = $this->file('/views/company/hr/employees/show.php');
        $ctrl = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $model = $this->file('/app/models/HrModels.php');

        $this->record(
            'M0 additive migration rateb_hr_decisions + disciplinary legacy_employee_id',
            str_contains($mig, 'rateb_hr_decisions')
            && str_contains($mig, 'legacy_employee_id')
            && !preg_match('/\bDROP\b/i', $mig)
            && !preg_match('/\bCREATE\s+TABLE\s+rateb_hrm_disciplinary/i', $mig)
        );

        $this->record(
            'M1 all decision types present',
            str_contains($svc, "'promotion'")
            && str_contains($svc, "'salary_adjustment'")
            && str_contains($svc, "'transfer'")
            && str_contains($svc, "'salary_stop'")
            && str_contains($svc, "'absence_deduction'")
            && str_contains($svc, "'salary_movement'")
            && str_contains($svc, "'termination'")
        );

        $this->record(
            'M2 disciplinary linked to rateb_employees (reuse HRMS table)',
            str_contains($disc, 'rateb_hrm_disciplinary_actions')
            && str_contains($disc, 'legacy_employee_id')
            && str_contains($disc, 'ensureProfileForEmployee')
            && !str_contains($disc, 'CREATE TABLE')
            && !preg_match('/\bclass\s+Disciplinary2\b/', $disc)
        );

        $this->record(
            'M3 request → Oversight/Matrix; no new Approval Engine',
            str_contains($svc, 'notifyPendingSubmission')
            && str_contains($oversight, "'hr_decision'")
            && str_contains($oversight, 'HrDecisionService')
            && str_contains($matrix, 'SOURCE_DECISION')
            && str_contains($matrix, "'hr_decision'")
            && !str_contains($svc, 'ApprovalEngine')
            && !str_contains($svc, 'WorkflowService2')
            && !str_contains($oversight, 'ApprovalEngine3')
        );

        $this->record(
            'M4 inbox actionable hr_decision; expenses still deferred',
            str_contains($inbox, "'hr_decision'")
            && str_contains($inbox, 'ACTIONABLE_SOURCES')
            && preg_match("/ACTIONABLE_SOURCES\s*=\s*\[[^\]]*hr_decision/", $inbox) === 1
            && str_contains($inbox, "'expense' => 0")
            && str_contains($inbox, 'they stay in Accounting oversight')
            && !str_contains($inbox, 'HR Decisions module not present')
        );

        $this->record(
            'M5 execute once CAS; approve then execute',
            str_contains($svc, 'function execute')
            && str_contains($svc, "status = 'approved' AND executed_at IS NULL")
            && str_contains($svc, 'finalizeApprove')
            && str_contains($svc, '$this->execute(')
            && str_contains($svc, 'hr_decision_already_executed')
        );

        $this->record(
            'M6 salary + termination security (no mutate before approved)',
            str_contains($svc, 'SENSITIVE_TYPES')
            && str_contains($svc, "'termination'")
            && str_contains($svc, "'salary_adjustment'")
            && str_contains($svc, "hr_decision_not_approved")
            && str_contains($svc, "'terminated'")
            && str_contains($svc, 'maybeAuditOpsSalaryChange')
            && !str_contains($svc, 'generatePayrollLines')
        );

        $this->record(
            'M7 tenant isolation + RBAC routes',
            str_contains($svc, 'company_id = :cid')
            && str_contains($disc, 'company_id = :cid')
            && str_contains($ops, "hr/decisions")
            && str_contains($ops, 'HrDecisionsController')
            && str_contains($ops, 'HrDisciplinaryController')
            && str_contains($ops, "rateb_erp_mw('hr', '', 'hr-employees')")
            && str_contains($menu, 'hr/decisions')
            && str_contains($menu, 'hr/disciplinary')
        );

        $this->record(
            'M8 audit create/approve/reject/execute',
            str_contains($svc, 'hr_decision_create')
            && str_contains($svc, 'hr_decision_approve')
            && str_contains($svc, 'hr_decision_reject')
            && str_contains($svc, 'hr_decision_execute')
            && str_contains($disc, 'hr_disciplinary_create')
        );

        $this->record(
            'M9 Employee 360 decisions + violations',
            str_contains($svc360, "TAB_DECISIONS")
            && str_contains($svc360, 'tabDecisions')
            && str_contains($svc360, 'HrDisciplinaryService')
            && str_contains($tab360, "tab === 'decisions'")
            && str_contains($show360, 'hr_360_tab_decisions')
            && str_contains($model, 'class HrDecision')
            && str_contains($ctrl, 'class HrDecisionsController')
            && str_contains($ctrl, 'class HrDisciplinaryController')
        );

        $this->record(
            'M10 no payroll redesign / no manager hierarchy / no Phase N',
            !str_contains($svc, 'manager_user_id')
            && !str_contains($svc, 'ApprovalEngine')
            && !preg_match('/generatePayrollLines|postPayroll|AccountingService/', $svc)
            && !str_contains($menu, "'performance'")
            && !str_contains($menu, 'hr/performance')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-M-DECISIONS-DISCIPLINARY-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-M-DECISIONS-DISCIPLINARY-CERTIFICATION.md')
            )
        );

        return $this->results;
    }

    private function file(string $rel): string
    {
        $path = RATEB_ROOT . $rel;
        $this->record('file exists ' . $rel, is_file($path));

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail !== '' ? $detail : ($passed ? 'ok' : 'fail'),
        ];
        echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    }
}
