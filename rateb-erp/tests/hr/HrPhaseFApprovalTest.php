<?php

declare(strict_types=1);

/**
 * Phase F — HR Approval Inbox unification (source analysis).
 *
 * Run: php tests/hr/run-hr-phase-f-approval-tests.php
 */
final class HrPhaseFApprovalTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testInboxServiceExists();
        $this->testReusesOversightService();
        $this->testDoesNotApprove();
        $this->testCompanyScopeGuard();
        $this->testHrSourceKeysOnly();
        $this->testDecisionsExpensesDeferred();
        $this->testRouteRegistered();
        $this->testMenuWired();
        $this->testCompanyApproveStillBlocked();
        $this->testPayrollWorkflowUntouched();
        $this->testNoApprovalEngine2();
        $this->testBadgeAggregatesHrRoutes();
        $this->testNormalizeApprovalItemShape();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testInboxServiceExists(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = str_contains($src, 'final class HrApprovalInboxService')
            && str_contains($src, 'function inbox(')
            && str_contains($src, 'function normalize(');
        $this->record('HrApprovalInboxService aggregator exists', $ok);
    }

    private function testReusesOversightService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = str_contains($src, 'new ApprovalOversightService()')
            && str_contains($src, "listPending(\$companyId, 'hr'")
            && str_contains($src, 'Reuses ApprovalOversightService');
        $this->record('Inbox reuses ApprovalOversightService (no engine rewrite)', $ok);
    }

    private function testDoesNotApprove(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
        // Phase J: decide is allowed via Oversight::process — must NOT call domain finalizers directly.
        $ok = !str_contains($src, 'approveLeave')
            && !str_contains($src, 'approvePayroll')
            && !str_contains($src, 'rejectLeave')
            && str_contains($src, 'ApprovalOversightService')
            && str_contains($src, '->process(');
        $ok = $ok && preg_match(
            '/final class HrApprovalInboxController[\s\S]*?\nfinal class /',
            $ctrl,
            $m
        ) === 1;
        $block = $m[0] ?? '';
        $ok = $ok
            && str_contains($block, 'function index')
            && str_contains($block, 'function decide')
            && !preg_match('/HrService\(\)->approveLeave/', $block)
            && !preg_match('/function\s+approve\s*\(/', $block);
        $this->record('Inbox decide only via Oversight process (no direct domain approve)', $ok);
    }

    private function testCompanyScopeGuard(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = str_contains($src, 'companyId < 1')
            && str_contains($src, '!== $companyId')
            && str_contains($src, 'never leak another company');
        $this->record('Inbox enforces company scope', $ok);
    }

    private function testHrSourceKeysOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = str_contains($src, "'hr_leave'")
            && str_contains($src, "'hr_permission'")
            && str_contains($src, "'hr_request'")
            && str_contains($src, "'hr_payroll'")
            && str_contains($src, 'HR_SOURCE_KEYS');
        $this->record('Inbox covers leave/permission/request/payroll sources', $ok);
    }

    private function testDecisionsExpensesDeferred(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        // Phase M: decisions are actionable; expenses remain deferred.
        $ok = str_contains($src, "'decision' => 0")
            && str_contains($src, "'expense' => 0")
            && str_contains($src, 'deferred')
            && str_contains($src, 'hr_decision')
            && str_contains($src, 'Expenses pending queue not present');
        $this->record('Expenses deferred; decisions wired (Phase M)', $ok);
    }

    private function testRouteRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
        $ok = str_contains($src, "hr/approvals-inbox")
            && str_contains($src, 'HrApprovalInboxController');
        $this->record('Route hr/approvals-inbox registered', $ok);
    }

    private function testMenuWired(): void
    {
        $menu = (string) file_get_contents(RATEB_ROOT . '/config/hr-menu.php');
        $ok = str_contains($menu, 'hr_pending_actions')
            && str_contains($menu, 'hr/approvals-inbox');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = $ok && str_contains($ar, 'عمليات بانتظار إجراء');
        $this->record('Menu + Arabic label عمليات بانتظار إجراء wired', $ok);
    }

    private function testCompanyApproveStillBlocked(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
        $ok = preg_match(
            '/hr\/leaves\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
            $src
        ) === 1;
        $ok = $ok && preg_match(
            '/hr\/payroll\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
            $src
        ) === 1;
        $ok = $ok && preg_match(
            '/hr\/requests\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
            $src
        ) === 1;
        $this->record('Company approve routes remain blocked for leave/request/payroll', (bool) $ok);
    }

    private function testPayrollWorkflowUntouched(): void
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
        $this->record('Payroll draft→approved→posted workflow unchanged', (bool) $ok);
    }

    private function testNoApprovalEngine2(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = !str_contains($src, 'ApprovalEngine2')
            && !str_contains($src, 'WorkflowEngine2')
            && !str_contains($src, 'CREATE TABLE');
        $this->record('No ApprovalEngine2 / new workflow tables', $ok);
    }

    private function testBadgeAggregatesHrRoutes(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/config/app.php');
        $ok = str_contains($src, "path === 'hr/approvals-inbox'")
            && str_contains($src, "counts['hr/leaves']")
            && str_contains($src, "counts['hr/payroll']");
        $this->record('Nav badge aggregates HR pending routes for inbox', $ok);
    }

    private function testNormalizeApprovalItemShape(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
        $ok = str_contains($src, "'type'")
            && str_contains($src, "'company_id'")
            && str_contains($src, "'current_status'")
            && str_contains($src, "'source_url'")
            && (str_contains($src, "'allowed_action'") || str_contains($src, 'allowed_action'));
        $this->record('ApprovalItem normalize shape present (in-memory DTO)', $ok);
    }
}
