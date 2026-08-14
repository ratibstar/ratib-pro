<?php

declare(strict_types=1);

/**
 * Phase G — HR Approval Matrix governance overlay (source analysis).
 *
 * Run: php tests/hr/run-hr-phase-g-approval-matrix-tests.php
 */
final class HrPhaseGApprovalMatrixTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testMatrixServiceExists();
        $this->testSupportedSources();
        $this->testOversightHooked();
        $this->testNoDomainStatusUpdateInMatrix();
        $this->testFinalizersRemainInOversight();
        $this->testVersionSnapshotBinding();
        $this->testAdditiveMigration();
        $this->testNoEapOrLegacyWorkflow();
        $this->testNoApprovalEngine2();
        $this->testCompanyApproveStillBlocked();
        $this->testPayrollUntouched();
        $this->testUndoResetsProgress();
        $this->testApproverTypesNoManagerInvention();
        $this->testCertificateUsesRequestType();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testMatrixServiceExists(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'final class HrApprovalMatrixService')
            && str_contains($src, 'function gateOversightDecision')
            && str_contains($src, 'OUTCOME_STAGE_ADVANCED')
            && str_contains($src, 'OUTCOME_PASSTHROUGH');
        $this->record('HrApprovalMatrixService governance overlay exists', $ok);
    }

    private function testSupportedSources(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, "SOURCE_LEAVE = 'hr_leave'")
            && str_contains($src, "SOURCE_PERMISSION = 'hr_permission'")
            && str_contains($src, "SOURCE_REQUEST = 'hr_request'")
            && !str_contains($src, 'hr_payroll');
        $this->record('Matrix covers leave/permission/request only (not payroll)', $ok);
    }

    private function testOversightHooked(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
        $ok = str_contains($src, 'HrApprovalMatrixService')
            && str_contains($src, 'gateOversightDecision')
            && str_contains($src, 'OUTCOME_STAGE_ADVANCED');
        $this->record('ApprovalOversightService gates HR decide via matrix', $ok);
    }

    private function testNoDomainStatusUpdateInMatrix(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = !str_contains($src, 'approveLeave')
            && !str_contains($src, 'rejectLeave')
            && !str_contains($src, 'setEmployeeRequestStatus')
            && !str_contains($src, 'UPDATE rateb_leave_requests')
            && !str_contains($src, 'UPDATE rateb_hr_permission_requests')
            && !str_contains($src, 'UPDATE rateb_hr_employee_requests');
        $this->record('Matrix service does not mutate domain status', $ok);
    }

    private function testFinalizersRemainInOversight(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
        $ok = preg_match(
            '/hr_leave[\s\S]*?approveLeave[\s\S]*?hr_permission[\s\S]*?setHrStatus[\s\S]*?setEmployeeRequestStatus/',
            $src
        ) === 1;
        $this->record('Final stage still uses existing Oversight domain finalizers', (bool) $ok);
    }

    private function testVersionSnapshotBinding(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/248_hr_phase_g_approval_matrix.sql');
        $ok = str_contains($src, 'stages_snapshot_json')
            && str_contains($src, 'matrix_version')
            && str_contains($mig, 'stages_snapshot_json')
            && str_contains($mig, 'matrix_version');
        $this->record('Progress binds matrix version + frozen stage snapshot', $ok);
    }

    private function testAdditiveMigration(): void
    {
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/248_hr_phase_g_approval_matrix.sql');
        $ok = str_contains($mig, 'rateb_hr_approval_matrices')
            && str_contains($mig, 'rateb_hr_approval_matrix_stages')
            && str_contains($mig, 'rateb_hr_approval_progress')
            && !str_contains($mig, 'ALTER TABLE rateb_leave_requests')
            && !str_contains($mig, 'ALTER TABLE rateb_hr_employee_requests')
            && !str_contains($mig, 'DROP TABLE');
        $this->record('Additive migration only (no domain ALTER/DROP)', $ok);
    }

    private function testNoEapOrLegacyWorkflow(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = !str_contains($src, 'rateb_eap_')
            && !preg_match('/\bnew\s+ApprovalWorkflowService\b/', $src)
            && !preg_match('/\bnew\s+WorkflowService\b/', $src)
            && !preg_match('/\bnew\s+WorkflowSubmissionService\b/', $src)
            && !preg_match('/use\s+Rateb\\\\App\\\\Services\\\\WorkflowService/', $src);
        $this->record('Matrix does not route through EAP or Legacy Workflow', (bool) $ok);
    }

    private function testNoApprovalEngine2(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = !str_contains($src, 'ApprovalEngine2')
            && !str_contains($src, 'WorkflowEngine')
            && str_contains($src, 'not an approval engine');
        $this->record('No ApprovalEngine2 / WorkflowEngine', $ok);
    }

    private function testCompanyApproveStillBlocked(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
        $ok = str_contains($routes, "hr/leaves/{id}/approve'), \$blockCompanyApprovalAction")
            || str_contains($routes, 'hr/leaves/{id}/approve"), $blockCompanyApprovalAction');
        // PowerShell-safe: check pattern without awkward quotes
        $ok = preg_match(
            "/hr\\/leaves\\/\\{id\\}\\/approve'\\)\\s*,\\s*\\\$blockCompanyApprovalAction/",
            $routes
        ) === 1
            && preg_match(
                "/hr\\/permission-requests\\/\\{id\\}\\/approve'\\)\\s*,\\s*\\\$blockCompanyApprovalAction/",
                $routes
            ) === 1
            && preg_match(
                "/hr\\/requests\\/\\{id\\}\\/approve'\\)\\s*,\\s*\\\$blockCompanyApprovalAction/",
                $routes
            ) === 1;
        $this->record('Company approve routes remain blocked', $ok);
    }

    private function testPayrollUntouched(): void
    {
        $oversight = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
        $hr = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match('/hr_payroll[\s\S]*?approvePayroll/', $oversight) === 1
            && str_contains($hr, 'function approvePayroll')
            && str_contains($hr, 'function postPayroll')
            && !str_contains(
                (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php'),
                'payroll'
            );
        $this->record('Payroll workflow untouched by matrix', $ok);
    }

    private function testUndoResetsProgress(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/ApprovalOversightService.php');
        $ok = substr_count($src, 'resetProgress(') >= 3;
        $this->record('Oversight undo resets matrix progress for HR sources', $ok);
    }

    private function testApproverTypesNoManagerInvention(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/248_hr_phase_g_approval_matrix.sql');
        $ok = str_contains($mig, "'oversight','user','role'")
            && !str_contains($src, 'direct_manager')
            && !str_contains($src, 'manager_id')
            && str_contains($src, 'getUserRoleIds');
        $this->record('Approver types limited to oversight/user/role (no invented manager tree)', $ok);
    }

    private function testCertificateUsesRequestType(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $lookup = (string) file_get_contents(RATEB_ROOT . '/app/services/FormLookupService.php');
        $ok = str_contains($src, 'resolveRequestType')
            && str_contains($src, 'request_type')
            && str_contains($lookup, 'salary_certificate')
            && !str_contains($src, 'CertificateWorkflow');
        $this->record('Certificate uses employee request_type (no separate workflow)', $ok);
    }
}
