<?php

declare(strict_types=1);

/**
 * Phase T — HR Enterprise Hardening (T0–T9 structural gates).
 *
 * Run: php tests/hr/run-hr-phase-t-tests.php
 */
final class HrPhaseTEnterpriseTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $ready = $this->file('/app/services/HrEnterpriseReadinessService.php');
        $cc = $this->file('/app/services/HrCommandCenterService.php');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $ctrl = $this->file('/app/controllers/Company/HrControllers.php');
        $ext = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $menu = $this->file('/config/hr-menu.php');
        $svc360 = $this->file('/app/services/HrEmployee360Service.php');
        $show = $this->file('/views/company/hr/employees/show.php');
        $tabView = $this->file('/views/company/hr/employees/360-tab.php');
        $saudi = $this->file('/app/services/HrSaudiComplianceService.php');
        $inbox = $this->file('/app/services/HrApprovalInboxService.php');
        $matrix = $this->file('/app/services/HrApprovalMatrixService.php');
        $inboxView = $this->file('/views/company/hr/approvals/inbox.php');
        $integ = $this->file('/app/services/HrEmployeeIntegrityService.php');
        $ops = $this->file('/app/services/HrOpsAutomationService.php');
        $cron = $this->file('/app/services/CronService.php');
        $mig255 = $this->file('/migrations/255_hr_phase_q_ops_automation.sql');
        $wf = $this->file('/app/services/HrWorkforceIntelligenceService.php');
        $ana = $this->file('/app/services/HrAnalyticsService.php');
        $ess = $this->file('/app/services/HrEssEmployeeResolverService.php');
        $ess360 = $this->file('/app/services/HrEss360Service.php');
        $manager = $this->file('/app/services/HrManagerTeamService.php');
        $apiEss = $this->file('/app/controllers/Api/HrEssPhasePController.php');
        $apiMgr = $this->file('/app/controllers/Api/HrManagerTeamController.php');
        $en = $this->file('/config/lang/en.php');
        $ar = $this->file('/config/lang/ar.php');
        $rootDocs = dirname(RATEB_ROOT) . '/docs/hr';

        $this->record(
            'T0 UX unification — Command Center first, no duplicate Saudi reports menu',
            str_contains($menu, "'route' => 'hr'")
            && str_contains($menu, 'hr_command_center')
            && str_contains($menu, 'Command Center is the primary entry')
            && substr_count($menu, 'hr/saudi-compliance') === 1
            && !str_contains($menu, 'saudi-reports')
            && str_contains($menu, 'hr_reports_hub_ops')
            && str_contains($en, 'hr_reports_hub_ops')
            && str_contains($ar, 'hr_reports_hub_ops')
            && str_contains($show, 'hr_360_tab_saudi')
            && str_contains($show, 'hr_360_tab_risk')
            && str_contains($tabView, "\$tab === 'saudi'")
            && str_contains($tabView, "\$tab === 'risk'")
            && str_contains($en, 'hr_360_tab_saudi')
            && str_contains($ar, 'hr_360_tab_risk')
        );

        $this->record(
            'T1 Employee 360 binds existing sources only (no new SoT, no N+1 loop)',
            str_contains($svc360, "TAB_SAUDI = 'saudi'")
            && str_contains($svc360, "TAB_RISK = 'risk'")
            && str_contains($svc360, 'function loadTab')
            && str_contains($svc360, 'employeeComplianceProfile')
            && str_contains($svc360, 'tabWorkforceRisk')
            && str_contains($saudi, 'WHERE e.company_id = :cid AND e.id = :eid')
            && str_contains($saudi, 'LIMIT 1')
            && !str_contains($svc360, 'rateb_employees_360')
            && !str_contains($svc360, 'CREATE TABLE')
            && !str_contains($svc360, 'Employee2')
            && str_contains($svc360, 'Does not mutate domain state')
        );

        $this->record(
            'T2 Approval Inbox uses existing matrix — stage + history + pending/final',
            str_contains($matrix, 'function decisionContext')
            && str_contains($matrix, 'stages_history')
            && str_contains($matrix, "\$state = 'current'")
            && str_contains($inbox, 'stages_history')
            && str_contains($inbox, 'pending_or_final')
            && str_contains($inbox, 'HrApprovalMatrixService')
            && str_contains($inbox, 'ApprovalOversightService')
            && !str_contains($inbox, 'class HrApprovalEngine')
            && !str_contains($inbox, 'ApprovalEngine2')
            && str_contains($inboxView, 'stages_history')
            && str_contains($inboxView, 'progress_status')
        );

        $this->record(
            'T3 Data integrity compact READ-ONLY on Command Center',
            str_contains($ready, 'function compactIntegrityForCompany')
            && str_contains($cc, 'compactIntegrityForCompany')
            && str_contains($ctrl, "'integrity'")
            && str_contains($dash, 'hr_t_integrity')
            && str_contains($integ, 'Never mutates data')
            && str_contains($integ, 'No automatic merge')
            && str_contains($integ, 'contracts_missing_employee')
            && str_contains($integ, 'payroll_salary_missing_employee')
            && str_contains($integ, 'profiles_orphan_legacy')
            && str_contains($ready, "'auto_repair' => false")
            && !str_contains($integ, 'DELETE FROM')
            && !preg_match('/function\s+(merge|autoRepair|autoDelete)/', $integ)
        );

        $certReady = is_file($rootDocs . '/HR-PHASE-T-PRODUCTION-READINESS.md')
            || is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-T-PRODUCTION-READINESS.md');
        $certEnt = is_file($rootDocs . '/HR-PHASE-T-ENTERPRISE-CERTIFICATION.md')
            || is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-T-ENTERPRISE-CERTIFICATION.md');

        $this->record(
            'T4 Production readiness inventory (247–257, cron, flags, blockers)',
            str_contains($ready, '247_hr_phase_b_ess_user_company_index.sql')
            && str_contains($ready, '257_hr_phase_s_workforce_intelligence.sql')
            && str_contains($ready, 'hr_employment_contract_status')
            && str_contains($ready, 'hr_employment_contract_alerts')
            && str_contains($ready, 'hr_ops_automation')
            && str_contains($ready, 'HR_PAYROLL_ACCOUNTING_ENABLED')
            && str_contains($ready, 'gosi_wps_external_send')
            && str_contains($ready, 'function productionBlockers')
            && str_contains($ready, 'deployment_prerequisites')
            && str_contains($ready, 'indexes_notes')
            && str_contains($cron, 'hr_ops_automation')
            && $certReady
        );

        $this->record(
            'T5 Security — tenant, RBAC, ESS, manager, salary, docs, CSRF, no client company_id',
            str_contains($svc360, 'WHERE id = :id AND company_id = :cid')
            && str_contains($ctrl, "'can_view_salary' => \$rbacReady")
            && str_contains($ctrl, ': false')
            && str_contains($ess, 'AND company_id = :cid')
            && str_contains($ess360, 'Never trusts client employee_id')
            && str_contains($manager, 'not_team_member')
            && str_contains($svc360, 'entity_id = :eid')
            && str_contains($svc360, 'rateb_hr_documents')
            && str_contains($svc360, "\$profile['salary_base'] = null")
            && str_contains($ctrl, 'validateCsrf')
            && str_contains($apiEss, 'TenantContext::companyId')
            && str_contains($apiMgr, 'TenantContext')
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $svc360)
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $inbox)
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $apiEss)
        );

        $this->record(
            'T6 Automation — idempotency, unique ledger, retry safety, audit',
            str_contains($ops, 'claimReminder')
            && str_contains($mig255, 'UNIQUE KEY uq_hr_ops_reminder')
            && str_contains($ops, 'return false')
            && str_contains($ops, 'hr_ops_automation_run')
            && str_contains($ops, 'AuditService')
            && str_contains($cron, 'HrOpsAutomationService')
            && str_contains($ops, 'period_key')
            && !str_contains($ops, 'approveLeave')
            && !str_contains($ops, 'postPayroll')
        );

        $this->record(
            'T7 Saudi GOSI/WPS readiness only — connectors OFF, external_sent 0',
            str_contains($saudi, 'external_send_enabled\' => false')
            && (str_contains($saudi, '\'external_sent\' => 0') || str_contains($saudi, 'external_sent = 0'))
            && str_contains($svc360, "'external_sent' => 0")
            && str_contains($ready, "'external_send_enabled' => false")
            && !str_contains($saudi, 'curl_')
            && !str_contains($saudi, 'GOSI_API')
            && !str_contains($ext, 'external_sent = 1')
            && !str_contains($ctrl, 'external_sent = 1')
        );

        $this->record(
            'T8 Performance — lazy 360, bounded CC/inbox/analytics, no 360-in-loop',
            str_contains($svc360, 'function loadTab')
            && str_contains($cc, 'LIST_LIMIT')
            && str_contains($cc, 'SEARCH_LIMIT')
            && str_contains($inbox, 'min(500, $limit)')
            && str_contains($wf, 'LIST_LIMIT')
            && str_contains($ana, 'REPORT_LIMIT')
            && !str_contains($cc, 'HrEmployee360Service')
            && !str_contains($cc, 'employeeComplianceProfiles')
            && !str_contains($svc360, 'employeeComplianceProfiles')
            && !preg_match('/foreach\s*\(\s*\$employees/i', $cc)
            && str_contains($ess360, 'company_id = :cid AND employee_id = :eid')
        );

        $this->record(
            'T9 Full hardening close — B–S runners, certs, no Phase U / no 258 / no engine rewrite',
            $certEnt
            && $certReady
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-c-security-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-d-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-e-accounting-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-f-approval-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-g-approval-matrix-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-h-matrix-governance-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-h2-leave-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-i-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-j-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-k-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-l-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-m-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-n-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-o-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-p-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-q-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-r-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-s-tests.php')
            && !is_file(RATEB_ROOT . '/migrations/258_hr_phase_u.sql')
            && !is_file(RATEB_ROOT . '/tests/hr/HrPhaseUTest.php')
            && !str_contains($ready, 'Phase U')
            && !str_contains($cc, 'ApprovalEngine')
            && !str_contains($svc360, 'generatePayrollLines')
            && !str_contains($svc360, 'Flutter')
            && !str_contains($svc360, 'manager_id')
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
