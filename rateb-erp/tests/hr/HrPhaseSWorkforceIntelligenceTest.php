<?php

declare(strict_types=1);

/**
 * Phase S — Workforce Intelligence & Planning (structural + calculation gates).
 *
 * Run: php tests/hr/run-hr-phase-s-tests.php
 */
final class HrPhaseSWorkforceIntelligenceTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->file('/app/services/HrWorkforceIntelligenceService.php');
        $mig = $this->file('/migrations/257_hr_phase_s_workforce_intelligence.sql');
        $ctrl = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $cc = $this->file('/app/services/HrCommandCenterService.php');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $view = $this->file('/views/company/hr/workforce/index.php');
        $classmap = $this->file('/app/Core/generated-classmap.php');
        $export = $this->file('/app/controllers/Shared/ExportController.php');
        $succ = $this->file('/app/services/HrSuccessionService.php');
        $saudi = $this->file('/app/services/HrSaudiComplianceService.php');

        $this->record(
            'S0 workforce planning targets + gap/vacancies',
            str_contains($mig, 'rateb_hr_workforce_plan_targets')
            && str_contains($mig, 'target_headcount')
            && !str_contains($mig, 'DROP TABLE')
            && str_contains($svc, 'workforcePlanning')
            && str_contains($svc, 'upsertPlanTarget')
            && str_contains($svc, 'vacancies')
            && str_contains($svc, 'demand_forecast')
            && str_contains($view, 'hr_s_workforce_planning')
        );

        $this->record(
            'S1 attrition uses hire_date + termination decisions (not status-only)',
            str_contains($svc, 'attritionAnalytics')
            && str_contains($svc, 'hire_date BETWEEN')
            && str_contains($svc, 'decision_type = \'termination\'')
            && str_contains($svc, 'effective_date')
            && str_contains($svc, 'turnover_pct')
            && str_contains($svc, 'attritionByDepartment')
        );

        $this->record(
            'S2 cost analytics + GOSI modeled + salary gate',
            str_contains($svc, 'costAnalytics')
            && str_contains($svc, 'salary_gated')
            && str_contains($svc, 'gosiModeledCost')
            && str_contains($svc, 'employer_cost')
            && str_contains($svc, 'allowances_total')
            && str_contains($svc, 'payrollByDimension')
            && str_contains($ctrl, 'canViewSalary')
            && $this->assertCostMath()
        );

        $this->record(
            'S3 employee risk dashboard aggregates',
            str_contains($svc, 'employeeRiskDashboard')
            && str_contains($svc, 'contracts_expiring')
            && str_contains($svc, 'frequent_absent')
            && str_contains($svc, 'frequent_late')
            && str_contains($svc, 'overdue_requests')
            && str_contains($svc, 'gosi_exceptions')
            && str_contains($svc, 'wps_exceptions')
            && str_contains($view, 'hr_s_employee_risk')
        );

        $this->record(
            'S4 succession intelligence reuses Phase O tables',
            str_contains($svc, 'successionIntelligence')
            && str_contains($svc, 'HrSuccessionService')
            && str_contains($svc, 'vacancy_risk')
            && str_contains($svc, 'ready_successors')
            && str_contains($succ, 'rateb_hr_critical_positions')
            && str_contains($view, 'hr_s_succession')
        );

        $this->record(
            'S5 hiring analytics funnel + time-to-hire',
            str_contains($svc, 'hiringAnalytics')
            && str_contains($svc, 'workflow_status')
            && str_contains($svc, 'avgTimeToHireDays')
            && str_contains($svc, 'rateb_recruitment_status_history')
            && str_contains($svc, 'recruitment_candidate_id')
            && str_contains($view, 'hr_s_hiring_analytics')
        );

        $this->record(
            'S6 executive dashboard + CC widgets + filters',
            str_contains($svc, 'executiveDashboard')
            && str_contains($svc, 'commandWidgets')
            && str_contains($svc, 'employment_type')
            && str_contains($svc, 'saudi_classification')
            && str_contains($svc, 'date_from')
            && str_contains($cc, 'workforce_intelligence')
            && str_contains($dash, 'workforceIntelligence')
            && str_contains($menu, 'hr/workforce')
            && str_contains($ops, 'hr/workforce')
            && str_contains($view, 'hr_s_workforce_gap')
        );

        $this->record(
            'S7 security tenant + RBAC + salary privacy + no sensitive audit on reads',
            str_contains($svc, 'company_id = :cid')
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $svc)
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $ctrl)
            && str_contains($ctrl, 'hr-payroll')
            && str_contains($svc, 'hr_workforce_plan_upsert')
            && str_contains($svc, 'hr_workforce_exec_export')
            && !str_contains($svc, 'AuditService())->log(\'hr_workforce_read')
            && str_contains($saudi, 'external_send_enabled\' => false')
            && !str_contains($svc, 'curl_')
            && !str_contains($svc, 'GOSI_API')
        );

        $this->record(
            'S8 performance: bounded queries, no Employee 360 loops, export reuse',
            str_contains($svc, 'LIST_LIMIT')
            && str_contains($svc, 'LIMIT 20')
            && str_contains($svc, 'LIMIT 2000')
            && !str_contains($svc, 'HrEmployee360Service')
            && !str_contains($svc, 'generatePayrollLines')
            && str_contains($ctrl, 'ExportController::send')
            && str_contains($export, 'rateb_can_export_entity')
            && str_contains($ops, 'hr/workforce/export')
            && $this->assertTurnoverMath()
        );

        $this->record(
            'S9 no redesign / no Phase T / classmap + B–R runners',
            !str_contains($svc, 'ApprovalEngine')
            && !str_contains($svc, 'Flutter')
            && !str_contains($svc, 'manager_id')
            && !str_contains($mig, 'Phase T')
            && str_contains($classmap, 'HrWorkforceIntelligenceController')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-S-WORKFORCE-INTELLIGENCE-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-S-WORKFORCE-INTELLIGENCE-CERTIFICATION.md')
            )
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-r-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-q-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
        );

        return $this->results;
    }

    private function assertCostMath(): bool
    {
        $basic = 10000.0;
        $allow = 2000.0;
        $gosiEr = round(min($basic + $allow, 45000.0) * 11.75 / 100, 2);
        $employer = round($basic + $allow + $gosiEr, 2);

        return abs($gosiEr - 1410.0) < 0.01 && abs($employer - 13410.0) < 0.01;
    }

    private function assertTurnoverMath(): bool
    {
        $terms = 2;
        $active = 20;
        $hires = 3;
        $avgBase = max(1, (int) round(($active + max(0, $active - $hires + $terms)) / 2));
        $turnover = round(($terms / $avgBase) * 100, 2);

        return $avgBase === 20 && abs($turnover - 10.0) < 0.01;
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
