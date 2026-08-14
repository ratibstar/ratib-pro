<?php

declare(strict_types=1);

/**
 * Phase N — HR Command Center (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-n-tests.php
 */
final class HrPhaseNCommandCenterTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $svc = $this->file('/app/services/HrCommandCenterService.php');
        $ctrl = $this->file('/app/controllers/Company/HrControllers.php');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $show = $this->file('/views/company/hr/employees/show.php');
        $ops = $this->file('/routes/modules/ops.php');
        $menu = $this->file('/config/hr-menu.php');
        $css = $this->file('/public/assets/css/hr-module.css');
        $js = $this->file('/public/assets/js/hr-module.js');
        $inbox = $this->file('/app/services/HrApprovalInboxService.php');
        $notify = $this->file('/app/services/NotificationService.php');

        $this->record(
            'N0 dashboard service + controller wired to overview route',
            str_contains($svc, 'class HrCommandCenterService')
            && str_contains($svc, 'function dashboard')
            && str_contains($ctrl, 'HrCommandCenterService')
            && str_contains($ctrl, 'company/hr/dashboard')
            && str_contains($menu, "'route' => 'hr'")
            && str_contains($menu, 'hr_command_center')
        );

        $this->record(
            'N1 quick actions cover employee/leave/letter/decision/disciplinary/contract/inbox',
            str_contains($svc, 'function quickActions')
            && str_contains($svc, 'hr/employees/create')
            && str_contains($svc, 'hr/leaves/create')
            && str_contains($svc, 'hr/requests/create')
            && str_contains($svc, 'hr/decisions/create')
            && str_contains($svc, 'hr/disciplinary/create')
            && str_contains($svc, 'hr/employment-contracts')
            && str_contains($svc, 'hr/approvals-inbox')
            && str_contains($dash, 'hr_cc_quick_actions')
        );

        $this->record(
            'N2 employee search bounded + lookup route → 360 URL',
            str_contains($svc, 'function searchEmployees')
            && str_contains($svc, 'SEARCH_LIMIT')
            && str_contains($svc, 'employee_code')
            && str_contains($svc, 'company_id = :cid')
            && str_contains($ctrl, 'function lookup')
            && str_contains($ops, 'hr/employees/lookup')
            && str_contains($js, 'data-hr-cc-search')
            && str_contains($dash, 'data-lookup-url')
        );

        $this->record(
            'N3 approval center leave/request/decision/permission',
            str_contains($svc, 'approval_center')
            && str_contains($svc, 'HrApprovalInboxService')
            && str_contains($dash, 'hr_cc_approval_you_have')
            && str_contains($dash, "'type' => 'decision'")
            && str_contains($dash, "'type' => 'permission'")
            && str_contains($dash, "'type' => 'leave'")
            && str_contains($dash, "'type' => 'request'")
            && str_contains($inbox, "'hr_decision'")
        );

        $this->record(
            'N4 Employee 360 hub links integrated',
            str_contains($svc, 'function employee360HubLinks')
            && str_contains($svc, 'employment')
            && str_contains($svc, 'decisions')
            && str_contains($svc, 'violations')
            && str_contains($svc, 'timeline')
            && str_contains($show, 'rateb-emp360-hub')
            && str_contains($show, 'hr_cc_360_hub')
            && str_contains($ctrl, 'hubLinks')
        );

        $this->record(
            'N5 alerts use NotificationService + domain pending/expiry',
            str_contains($svc, 'NotificationService')
            && str_contains($svc, 'listRecentForUser')
            && str_contains($svc, 'contracts_expiring')
            && str_contains($svc, 'upcoming_leaves')
            && str_contains($svc, 'pending_approvals')
            && str_contains($notify, 'function listRecentForUser')
            && str_contains($dash, 'hr_cc_alerts')
        );

        $this->record(
            'N6 UX: external CSS/JS, empty states, Arabic labels, no inline style/script blocks in dashboard logic',
            str_contains($dash, 'hr-module.css')
            && str_contains($dash, 'hr-module.js')
            && str_contains($dash, 'hr_cc_empty_')
            && str_contains($css, 'rateb-hr-cc-')
            && str_contains($js, 'initCommandCenterSearch')
            && !preg_match('/<style\b/i', $dash)
            && !preg_match('/style\s*=\s*"/i', $dash)
            && !preg_match('/<script(?![^>]*src=)/i', $dash)
        );

        $this->record(
            'N7 performance: LIMIT bounds, no employee full dump, company scoped',
            str_contains($svc, 'LIMIT')
            && str_contains($svc, 'company_id = :cid')
            && str_contains($svc, 'SEARCH_LIMIT')
            && str_contains($svc, 'LIST_LIMIT')
            && !preg_match('/foreach\s*\(\s*\$employees/i', $svc)
            && !str_contains($svc, 'SELECT * FROM rateb_employees WHERE company_id')
        );

        $this->record(
            'N8 security boundaries: no new engines / no SoT rewrite',
            !str_contains($svc, 'ApprovalEngine')
            && !str_contains($svc, 'generatePayrollLines')
            && !str_contains($svc, 'AccountingService')
            && !str_contains($svc, 'Employee2')
            && !str_contains($svc, 'CREATE TABLE')
            && str_contains($svc, 'rateb_employees')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-N-HR-COMMAND-CENTER-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-N-HR-COMMAND-CENTER-CERTIFICATION.md')
            )
        );

        $this->record(
            'N9 workforce tiles include late/on_leave + recent payroll/requests/decisions',
            str_contains($svc, 'late_today')
            && str_contains($svc, 'on_leave_today')
            && str_contains($svc, 'recentPayrolls')
            && str_contains($svc, 'recentRequests')
            && str_contains($svc, 'recentDecisions')
            && str_contains($dash, 'hr_cc_late_today')
            && str_contains($dash, 'hr_cc_recent_payrolls')
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
