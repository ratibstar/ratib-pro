<?php

declare(strict_types=1);

/**
 * Phase J — Actionable Approval Inbox (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-j-tests.php
 */
final class HrPhaseJApprovalInboxTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $inbox = $this->inbox();
        $matrix = $this->matrix();
        $ctrl = $this->ctrl();
        $ops = $this->ops();
        $view = $this->view();
        $ctrlBlock = $this->inboxControllerBlock($ctrl);

        $this->record(
            'J1 actionable sources leave/permission/request only',
            str_contains($inbox, 'ACTIONABLE_SOURCES')
            && str_contains($inbox, "'hr_leave'")
            && str_contains($inbox, "'hr_permission'")
            && str_contains($inbox, "'hr_request'")
            && str_contains($inbox, 'hr_inbox_payroll_not_actionable')
            && preg_match("/ACTIONABLE_SOURCES\s*=\s*\[[^\]]*hr_payroll/", $inbox) !== 1
        );

        $this->record(
            'J2 decide routes through Oversight::process',
            str_contains($inbox, 'function decide(')
            && str_contains($inbox, '->process(')
            && str_contains($inbox, 'ApprovalOversightService')
            && !str_contains($inbox, 'approveLeave')
            && !str_contains($inbox, 'approvePayroll')
            && !str_contains($inbox, 'rejectLeave')
        );

        $this->record(
            'J3 server resolves company + actor (no trusted POST ids)',
            str_contains($ctrlBlock, 'rateb_resolve_ops_company_id')
            && str_contains($ctrlBlock, "SessionManager::get('rateb_user_id')")
            && !preg_match('/input\(\s*[\'"]company_id[\'"]/', $ctrlBlock)
            && !preg_match('/input\(\s*[\'"]approver_id[\'"]/', $ctrlBlock)
            && !preg_match('/input\(\s*[\'"]employee_id[\'"]/', $ctrlBlock)
        );

        $this->record(
            'J4 cross-company decide fails',
            str_contains($inbox, 'resolveRecordCompanyId')
            && str_contains($inbox, '$recordCompanyId !== $companyId')
            && str_contains($inbox, 'access_denied')
        );

        $this->record(
            'J5 matrix canActorDecide + oversight/user/role',
            str_contains($matrix, 'function canActorDecide')
            && str_contains($matrix, "\$type === 'user'")
            && str_contains($matrix, "\$type === 'role'")
            && str_contains($matrix, "\$type === 'oversight'")
            && str_contains($matrix, 'actorHasCompanyHrDecideAuthority')
        );

        $this->record(
            'J6 reject path stage-authorized',
            preg_match(
                '/if \(\$action === \'reject\'\)[\s\S]*?actorMayAct[\s\S]*?markProgressRejected/',
                $matrix
            ) === 1
        );

        $this->record(
            'J7 oversight stage not open to all',
            str_contains($matrix, "\$type === 'oversight'")
            && str_contains($matrix, 'actorHasCompanyHrDecideAuthority')
            && !preg_match(
                '/\$type === \'oversight\'\s*\{\s*return true;/',
                $matrix
            )
        );

        $this->record(
            'J8 payroll view-only in inbox',
            str_contains($inbox, 'Payroll remains view-only')
            && str_contains($view, 'hr_inbox_payroll_view_only')
        );

        $this->record(
            'J9 legacy company approve routes still blocked',
            preg_match(
                '/hr\/leaves\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
                $ops
            ) === 1
            && preg_match(
                '/hr\/requests\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
                $ops
            ) === 1
            && preg_match(
                '/hr\/payroll\/\{id\}\/approve\'\),\s*\$blockCompanyApprovalAction/',
                $ops
            ) === 1
        );

        $this->record(
            'J10 decide route registered without inventing engine',
            str_contains($ops, 'hr/approvals-inbox/decide')
            && str_contains($ops, 'HrApprovalInboxController')
            && !preg_match('/\bclass\s+ApprovalEngine3\b/', $inbox)
            && !preg_match('/\bclass\s+HrWorkflowService2\b/', $inbox)
            && !preg_match('/\bCREATE\s+TABLE\b/i', $inbox)
        );

        $this->record(
            'J11 UI exposes approve/reject when can_act',
            str_contains($view, 'can_act')
            && str_contains($view, 'value="approve"')
            && str_contains($view, 'value="reject"')
            && str_contains($view, 'hr_inbox_awaiting_authorized_actor')
            && str_contains($view, 'hr_inbox_next_finalize')
        );

        $this->record(
            'J12 stage / history context exposed',
            str_contains($matrix, 'function decisionContext')
            && str_contains($matrix, 'next_outcome')
            && str_contains($matrix, 'last_actor_user_id')
            && str_contains($inbox, 'stage_name')
            && str_contains($inbox, 'next_outcome')
        );

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function inbox(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalInboxService.php');
    }

    private function matrix(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php');
    }

    private function ctrl(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/HrControllers.php');
    }

    private function ops(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/routes/modules/ops.php');
    }

    private function view(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/views/company/hr/approvals/inbox.php');
    }

    private function inboxControllerBlock(string $ctrl): string
    {
        if (preg_match('/final class HrApprovalInboxController[\s\S]*?\nfinal class /', $ctrl, $m) === 1) {
            return $m[0];
        }

        return '';
    }
}
