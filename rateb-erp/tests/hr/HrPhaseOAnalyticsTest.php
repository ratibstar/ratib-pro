<?php

declare(strict_types=1);

/**
 * Phase O — Organization + Succession + Analytics (structural gates).
 *
 * Run: php tests/hr/run-hr-phase-o-tests.php
 */
final class HrPhaseOAnalyticsTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $org = $this->file('/app/services/HrOrganizationService.php');
        $succ = $this->file('/app/services/HrSuccessionService.php');
        $ana = $this->file('/app/services/HrAnalyticsService.php');
        $cc = $this->file('/app/services/HrCommandCenterService.php');
        $ctrl = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $mig = $this->file('/migrations/253_hr_phase_o_succession.sql');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $orgView = $this->file('/views/company/hr/organization/index.php');
        $rep = $this->file('/views/company/hr/analytics/reports.php');

        $this->record(
            'O0 organization from departments/positions/employees + optional reporting',
            str_contains($org, 'class HrOrganizationService')
            && str_contains($org, 'rateb_hr_departments')
            && str_contains($org, 'rateb_employees')
            && str_contains($org, 'manager_profile_id')
            && str_contains($org, 'optional_hrms_soft_link_only')
            && str_contains($org, '360_url')
            && str_contains($ops, 'hr/organization')
            && str_contains($orgView, 'rateb-hr-org-dept')
            && str_contains($org, 'optionalManagersByEmployee')
        );

        $this->record(
            'O1 succession additive tables + readiness/skill gaps (no manager hierarchy invent)',
            str_contains($mig, 'rateb_hr_critical_positions')
            && str_contains($mig, 'rateb_hr_succession_candidates')
            && !preg_match('/\bDROP\b/i', $mig)
            && str_contains($succ, 'READINESS')
            && str_contains($succ, 'skill_gap_notes')
            && str_contains($succ, 'current_employee_id')
            && str_contains($ops, 'hr/succession')
            && !str_contains($succ, 'manager_user_id')
            && !str_contains($succ, 'ApprovalEngine')
        );

        $this->record(
            'O2 analytics headcount/dept/status/attendance/leave/payroll/contracts',
            str_contains($ana, 'function snapshot')
            && str_contains($ana, 'by_department')
            && str_contains($ana, 'by_status')
            && str_contains($ana, 'hireTerminate')
            && str_contains($ana, 'attendanceSummary')
            && str_contains($ana, 'leaveSummary')
            && str_contains($ana, 'contractsExpiring')
            && str_contains($ana, 'recruitmentSummary')
            && str_contains($ana, 'basic_salary + pl.allowances')
            && !str_contains($ana, 'pl.gross_salary')
            && str_contains($ops, 'hr/analytics')
        );

        $this->record(
            'O3 reports hub + ExportController reuse',
            str_contains($ctrl, 'class HrAnalyticsController')
            && str_contains($ctrl, 'function reports')
            && str_contains($ctrl, 'function export')
            && str_contains($ctrl, 'ExportController::send')
            && str_contains($ops, 'hr/reports-hub')
            && str_contains($ops, 'hr/reports-hub/export')
            && str_contains($rep, 'export-toolbar')
            && str_contains($menu, 'hr/reports-hub')
        );

        $this->record(
            'O4 command center analytics widgets',
            str_contains($cc, 'analytics_widgets')
            && str_contains($cc, 'commandWidgets')
            && str_contains($dash, 'hr_o_analytics_widgets')
            && str_contains($dash, 'analyticsWidgets')
        );

        $this->record(
            'O5 filters department/position/status/date range',
            str_contains($ana, 'date_from')
            && str_contains($ana, 'date_to')
            && str_contains($ana, 'department_id')
            && str_contains($ana, 'job_title_id')
            && str_contains($org, 'department_id')
            && str_contains($ctrl, 'filtersFromInput')
        );

        $this->record(
            'O6 export uses existing ExportController (no ExportEngine2)',
            str_contains($ctrl, 'ExportController::send')
            && !str_contains($ctrl, 'ExportEngine2')
            && !str_contains($ana, 'CREATE TABLE')
        );

        $this->record(
            'O7 performance: LIMIT/GROUP BY, company scoped, no full employee dump loops',
            str_contains($ana, 'REPORT_LIMIT')
            && str_contains($ana, 'company_id = :cid')
            && str_contains($org, 'EMP_LIMIT_PER_DEPT')
            && str_contains($org, 'GROUP BY')
            && !preg_match('/foreach\s*\(\s*\$allEmployees/i', $ana . $org)
        );

        $this->record(
            'O8 security: salary gated + tenant company_id + no SoT/engine rewrite',
            str_contains($ana, 'canViewSalary')
            && str_contains($ctrl, 'canViewSalary')
            && str_contains($rep, 'hr_360_salary_unauthorized')
            && !str_contains($ana, 'generatePayrollLines')
            && !str_contains($ana, 'AccountingService')
            && !str_contains($ana, 'ApprovalEngine')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-O-ANALYTICS-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-O-ANALYTICS-CERTIFICATION.md')
            )
        );

        $this->record(
            'O9 menu + routes for org/succession/analytics wired',
            str_contains($menu, 'hr/organization')
            && str_contains($menu, 'hr/succession')
            && str_contains($menu, 'hr/analytics')
            && str_contains($ctrl, 'class HrOrganizationController')
            && str_contains($ctrl, 'class HrSuccessionController')
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
