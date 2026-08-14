<?php

declare(strict_types=1);

/**
 * Phase H — HR Approval Matrix production governance (source + validator analysis).
 *
 * Run: php tests/hr/run-hr-phase-h-matrix-governance-tests.php
 */
final class HrPhaseHMatrixGovernanceTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testValidatorExists();
        $this->testRejectsUnknownApproverTypes();
        $this->testNoSilentCoercionInService();
        $this->testSaveRequiresValidation();
        $this->testDraftVsActivate();
        $this->testDeactivateExists();
        $this->testSpecificBeatsWildcard();
        $this->testContiguousStagesRequired();
        $this->testCompanyScopedRoleAndUserRules();
        $this->testRuntimeSelfApprovalGuard();
        $this->testAuditOnConfigChanges();
        $this->testNoEngineRewrite();
        $this->testSourcesUnchanged();
        $this->testCompanyApproveStillBlocked();
        $this->testDuplicateApproverWarning();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testValidatorExists(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = str_contains($src, 'final class HrApprovalMatrixValidator')
            && str_contains($src, 'function validate(')
            && str_contains($src, 'ALLOWED_APPROVER_TYPES');
        $this->record('HrApprovalMatrixValidator exists', $ok);
    }

    private function testRejectsUnknownApproverTypes(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = str_contains($src, 'approver_type_rejected:')
            && str_contains($src, "'oversight', 'user', 'role'")
            && !str_contains($src, 'manager')
            && !str_contains($src, 'department_head');
        $v = new \Rateb\App\Services\HrApprovalMatrixValidator();
        $res = $v->validate(0, 'hr_leave', '', 'Leave', [
            ['stage_order' => 1, 'code' => 's1', 'name' => 'S1', 'approver_type' => 'manager'],
        ], true);
        $ok = $ok && !$res['ok'] && in_array('approver_type_rejected:manager', $res['errors'], true);
        $this->record('Unknown approver types hard-rejected (no silent convert)', $ok);
    }

    private function testNoSilentCoercionInService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = !preg_match('/approver_type[^\n]*oversight[^\n]*in_array[\s\S]{0,80}\$atype = \'oversight\'/', $src)
            && str_contains($src, 'HrApprovalMatrixValidator')
            && str_contains($src, 'Unknown types must never silently pass');
        $this->record('Matrix service no longer coerces invalid approver_type to oversight', $ok);
    }

    private function testSaveRequiresValidation(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = preg_match('/function saveMatrix[\s\S]*?HrApprovalMatrixValidator[\s\S]*?matrix_validation_failed/', $src) === 1
            && str_contains($src, 'function validateMatrixConfig');
        $this->record('saveMatrix validates before persist', (bool) $ok);
    }

    private function testDraftVsActivate(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'bool $activate = false')
            && str_contains($src, 'function activateMatrix')
            && str_contains($src, '$enabled = $activate ? 1 : 0')
            && str_contains($src, 'hr_matrix_save_draft');
        $this->record('DRAFT vs ACTIVE via enabled + activateMatrix', $ok);
    }

    private function testDeactivateExists(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'function deactivateMatrix')
            && str_contains($src, 'in_flight_progress_keeps_snapshot')
            && str_contains($src, 'SET enabled = 0');
        $this->record('deactivateMatrix safe rollback without rewriting in-flight snapshots', $ok);
    }

    private function testSpecificBeatsWildcard(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'Specific request_type beats wildcard')
            && preg_match('/function resolveMatrix[\s\S]*?findMatrixRow\(\$companyId, \$sourceKey, \$requestType\)[\s\S]*?findMatrixRow\(\$companyId, \$sourceKey, \'\'\)/', $src) === 1;
        $this->record('Specific request_type beats wildcard; only enabled matrices resolve', (bool) $ok);
    }

    private function testContiguousStagesRequired(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = str_contains($src, 'stage_order_gap_or_non_contiguous');
        $v = new \Rateb\App\Services\HrApprovalMatrixValidator();
        $res = $v->validate(0, 'hr_leave', '', 'Leave matrix', [
            ['stage_order' => 1, 'code' => 'a', 'name' => 'A', 'approver_type' => 'oversight'],
            ['stage_order' => 3, 'code' => 'b', 'name' => 'B', 'approver_type' => 'oversight'],
        ], true);
        $ok = $ok && !$res['ok'] && in_array('stage_order_gap_or_non_contiguous', $res['errors'], true);
        $this->record('Stage orders must be contiguous 1..N', $ok);
    }

    private function testCompanyScopedRoleAndUserRules(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = str_contains($src, 'user_approver_cross_company')
            && str_contains($src, 'user_approver_inactive')
            && str_contains($src, 'role_approver_not_company_scoped')
            && str_contains($src, 'role_approver_cross_company');
        $this->record('User/role approvers must be company-scoped and valid', $ok);
    }

    private function testRuntimeSelfApprovalGuard(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'isSelfApprovalBlocked')
            && str_contains($src, 'resolveRequesterUserId')
            && str_contains($src, 'rateb_employees');
        $this->record('Runtime self-approval guard for user stages', $ok);
    }

    private function testAuditOnConfigChanges(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, 'hr_matrix_save_draft')
            && str_contains($src, 'hr_matrix_activate')
            && str_contains($src, 'hr_matrix_deactivate')
            && str_contains($src, 'AuditService');
        $this->record('Config changes audited via AuditService', $ok);
    }

    private function testNoEngineRewrite(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $val = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = !str_contains($src, 'rateb_eap_')
            && !preg_match('/\bnew\s+WorkflowService\b/', $src)
            && !str_contains($src, 'ApprovalEngine2')
            && !str_contains($val, 'UPDATE rateb_leave_requests');
        $this->record('No ApprovalEngine2 / EAP / Legacy Workflow / domain status writes', (bool) $ok);
    }

    private function testSourcesUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
        $ok = str_contains($src, "'hr_leave'")
            && str_contains($src, "'hr_permission'")
            && str_contains($src, "'hr_request'")
            && str_contains($src, 'hr_decision') // Phase M additive
            && !str_contains($src, 'hr_expense');
        $this->record('Supported sources include Phase G + M (no expenses engine)', $ok);
    }

    private function testCompanyApproveStillBlocked(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
        $ok = preg_match(
            "/hr\\/leaves\\/\\{id\\}\\/approve'\\)\\s*,\\s*\\\$blockCompanyApprovalAction/",
            $routes
        ) === 1;
        $this->record('Company approve routes remain blocked', $ok);
    }

    private function testDuplicateApproverWarning(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixValidator.php');
        $ok = str_contains($src, 'duplicate_approver_across_stages:');
        $v = new \Rateb\App\Services\HrApprovalMatrixValidator();
        $res = $v->validate(0, 'hr_leave', '', 'Leave', [
            ['stage_order' => 1, 'code' => 'a', 'name' => 'A', 'approver_type' => 'oversight'],
            ['stage_order' => 2, 'code' => 'b', 'name' => 'B', 'approver_type' => 'oversight'],
        ], true);
        $ok = $ok && in_array('duplicate_approver_across_stages:oversight', $res['warnings'], true);
        // company_id 0 still errors, but warnings should still be collected when stages normalize
        $this->record('Duplicate approvers produce warnings (no invented SoD hard-fail)', $ok);
    }
}
