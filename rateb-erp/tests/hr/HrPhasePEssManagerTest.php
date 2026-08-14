<?php

declare(strict_types=1);

/**
 * Phase P — ESS Parity + Manager Self-Service (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-p-tests.php
 */
final class HrPhasePEssManagerTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $ess360 = $this->file('/app/services/HrEss360Service.php');
        $phaseC = $this->file('/app/services/HrEssPhaseCService.php');
        $manager = $this->file('/app/services/HrManagerTeamService.php');
        $saudi = $this->file('/app/services/HrSaudiComplianceFoundationService.php');
        $payslip = $this->file('/app/services/HrEssPayslipDocumentService.php');
        $perm = $this->file('/app/services/HrEssPermissionRequestService.php');
        $inbox = $this->file('/app/services/HrApprovalInboxService.php');
        $api = $this->file('/routes/modules/api.php');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $mig = $this->file('/migrations/254_hr_phase_p_saudi_foundation.sql');
        $essView = $this->file('/views/company/hr/ess/index.php');
        $mgrView = $this->file('/views/company/hr/manager/index.php');
        $ext = $this->file('/app/controllers/Company/HrExtendedControllers.php');
        $apiP = $this->file('/app/controllers/Api/HrEssPhasePController.php');
        $apiM = $this->file('/app/controllers/Api/HrManagerTeamController.php');
        $letter = $this->file('/app/services/HrLetterIssueService.php');
        $resolver = $this->file('/app/services/HrEssEmployeeResolverService.php');
        $oversight = $this->file('/app/services/ApprovalOversightService.php');
        $matrix = $this->file('/app/services/HrApprovalMatrixService.php');

        $this->record(
            'file exists ESS 360 / manager / saudi / API controllers',
            $ess360 !== '' && $manager !== '' && $saudi !== '' && $apiP !== '' && $apiM !== ''
            && str_contains($ext, 'HrEssPortalController')
            && str_contains($ext, 'HrManagerPortalController')
        );

        $this->record(
            'P0 ESS simplified 360 + leave/requests/docs/payslips/decisions/notifications',
            str_contains($ess360, 'simplified360')
            && str_contains($ess360, 'leave_balances')
            && str_contains($ess360, 'leave_history')
            && str_contains($ess360, 'requests')
            && str_contains($ess360, 'documents')
            && str_contains($ess360, 'payslips')
            && str_contains($ess360, 'decisions')
            && str_contains($ess360, 'notifications')
            && str_contains($api, '/api/v1/hr/me/360')
            && str_contains($essView, 'hr_ess_portal')
        );

        $this->record(
            'P1 Manager My Team attendance/leave/requests/approvals/profiles',
            str_contains($manager, 'function myTeam')
            && str_contains($manager, 'function teamAttendance')
            && str_contains($manager, 'function teamLeave')
            && str_contains($manager, 'function teamRequests')
            && str_contains($manager, 'function teamApprovals')
            && str_contains($manager, 'function teamEmployeeProfile')
            && str_contains($manager, 'hrms_manager_profile_id')
            && str_contains($manager, 'soft-link')
            && str_contains($api, '/api/v1/hr/manager/team')
            && str_contains($mgrView, 'hr_manager_my_team')
        );

        $this->record(
            'P2 Approvals reuse Oversight + Matrix (no new engine)',
            str_contains($manager, 'HrApprovalInboxService')
            && str_contains($manager, '->decide(')
            && str_contains($inbox, 'ApprovalOversightService')
            && str_contains($inbox, 'HrApprovalMatrixService')
            && str_contains($inbox, 'employee_id')
            && !str_contains($manager, 'ApprovalEngine2')
            && !str_contains($apiM, 'ApprovalEngine')
            && is_file(RATEB_ROOT . '/app/services/ApprovalOversightService.php')
            && is_file(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php')
        );

        $this->record(
            'P3 Certificates via letter SoT (salary/employment/experience/EOS)',
            str_contains($phaseC, 'HrLetterIssueService::LETTER_TYPES')
            && str_contains($phaseC, 'isLetterType')
            && str_contains($phaseC, 'notifyPendingSubmission')
            && str_contains($phaseC, 'downloadLetter')
            && str_contains($letter, 'salary_certificate')
            && str_contains($letter, 'employment_certificate')
            && str_contains($letter, 'experience_letter')
            && str_contains($letter, 'end_of_service')
            && str_contains($letter, 'DocumentService')
            && str_contains($api, '/api/v1/hr/letters')
            && str_contains($ops, 'hr/ess/certificates')
        );

        $this->record(
            'P4 Payslips PDF from existing amounts (no payroll rebuild)',
            str_contains($payslip, 'application/pdf')
            && str_contains($payslip, 'HrLetterPdfRenderer')
            && !str_contains($payslip, 'generatePayroll')
            && !str_contains($payslip, 'calculatePayroll')
        );

        $this->record(
            'P5 Notifications via NotificationService + oversight notify',
            str_contains($ess360, 'NotificationService')
            && str_contains($phaseC, 'ApprovalOversightService::notifyPendingSubmission')
            && str_contains($perm, 'notifyPendingSubmission')
            && str_contains($perm, 'hr_permission')
            && str_contains($oversight, 'NotificationService')
        );

        $this->record(
            'P6 Security: resolver binding, team gate, salary privacy, document ownership',
            str_contains($resolver, 'company_id')
            && str_contains($resolver, 'user_id')
            && str_contains($manager, 'not_team_member')
            && str_contains($manager, 'canViewSalary')
            && str_contains($manager, 'salary_base')
            && str_contains($phaseC, 'Document ownership')
            && str_contains($phaseC, 'employee_id') !== false
            && str_contains($apiP, 'TenantContext::apiUserId')
            && str_contains($apiM, 'TenantContext::companyId')
            && !str_contains($manager, '$_GET[\'employee_id\']')
            && !str_contains($ess360, 'input(\'employee_id\'')
        );

        $this->record(
            'P7 Saudi HR foundation only (no external send)',
            str_contains($mig, 'rateb_hr_saudi_employment_fields')
            && str_contains($mig, 'rateb_hr_saudi_integration_audit')
            && str_contains($saudi, 'external_sent')
            && str_contains($saudi, 'external_send_enabled') 
            && str_contains($saudi, 'foundation_only')
            && str_contains($saudi, 'VALUES (:cid, :ch, :act, :st, :ps, 0,')
            && !str_contains($saudi, 'curl_')
            && !str_contains($saudi, 'file_get_contents(\'http')
            && str_contains($mgrView, 'hr_saudi_foundation')
        );

        $this->record(
            'P8 Admin menu + routes wired; mobile API parity endpoints present',
            str_contains($menu, 'hr/ess')
            && str_contains($menu, 'hr/manager')
            && str_contains($ops, 'HrEssPortalController')
            && str_contains($ops, 'HrManagerPortalController')
            && str_contains($api, '/api/v1/hr/manager/approvals/decide')
            && str_contains($api, '/api/v1/hr/decisions')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-P-ESS-MANAGER-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-P-ESS-MANAGER-CERTIFICATION.md')
            )
        );

        $this->record(
            'P9 B–O regression runners present',
            is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-o-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-n-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-l-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/HrPhaseOAnalyticsTest.php')
            && !str_contains($phaseC, 'ApprovalEngine2')
            && !str_contains($manager, 'class ApprovalEngine')
        );

        $classmap = $this->file('/app/Core/generated-classmap.php');
        $this->record(
            'Classmap includes Phase F–P bag controllers (autoload fix)',
            str_contains($classmap, 'HrApprovalInboxController')
            && str_contains($classmap, 'HrOrganizationController')
            && str_contains($classmap, 'HrEssPortalController')
            && str_contains($classmap, 'HrManagerPortalController')
            && str_contains($classmap, 'HrAnalyticsController')
            && str_contains($classmap, 'HrLettersController')
            && str_contains($classmap, 'HrDecisionsController')
        );

        $this->record(
            'MariaDB-safe headcount aliases (no AS active/terminated)',
            str_contains($this->file('/app/services/HrAnalyticsService.php'), 'active_count')
            && str_contains($this->file('/app/services/HrAnalyticsService.php'), 'terminated_count')
            && str_contains($this->file('/app/services/HrService.php'), 'active_count')
            && !preg_match('/AS\s+terminated\b/', $this->file('/app/services/HrAnalyticsService.php'))
        );

        $this->record(
            'Direct API bypass guards: no client employee_id trust in Phase P services',
            !preg_match('/\$_(GET|POST|REQUEST)\s*\[\s*[\'"]employee_id[\'"]\s*\]/', $ess360 . $manager . $phaseC)
            && str_contains($manager, 'resolveCurrentEmployee')
            && str_contains($ess360, 'resolveCurrentEmployee')
            && str_contains($matrix, 'canActorDecide')
        );

        return $this->results;
    }

    private function file(string $rel): string
    {
        $path = RATEB_ROOT . $rel;
        if (!is_file($path)) {
            $this->record('file exists ' . $rel, false, 'missing');

            return '';
        }
        $this->record('file exists ' . $rel, true);

        return (string) file_get_contents($path);
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
