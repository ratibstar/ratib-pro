<?php

declare(strict_types=1);

/**
 * Phase Q — HR Operations Automation (source / structural gates).
 *
 * Run: php tests/hr/run-hr-phase-q-tests.php
 */
final class HrPhaseQAutomationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $ops = $this->file('/app/services/HrOpsAutomationService.php');
        $cron = $this->file('/app/services/CronService.php');
        $cc = $this->file('/app/services/HrCommandCenterService.php');
        $dash = $this->file('/views/company/hr/dashboard.php');
        $ctrl = $this->file('/app/controllers/Company/HrControllers.php');
        $mig = $this->file('/migrations/255_hr_phase_q_ops_automation.sql');
        $notify = $this->file('/app/services/NotificationService.php');
        $oversight = $this->file('/app/services/ApprovalOversightService.php');
        $matrix = $this->file('/app/services/HrApprovalMatrixService.php');

        $this->record(
            'Q0 service + migration + Command Center wiring exist',
            str_contains($ops, 'class HrOpsAutomationService')
            && str_contains($mig, 'rateb_hr_ops_reminder_ledger')
            && str_contains($mig, 'rateb_hr_ops_automation_settings')
            && str_contains($cc, 'commandCenterOps')
            && str_contains($ctrl, 'hrTasks')
            && str_contains($dash, 'hr_q_hr_tasks')
        );

        $this->record(
            'Q1 contracts 30/15/7 milestones',
            str_contains($ops, 'processContractMilestones')
            && str_contains($ops, 'DEFAULT_CONTRACT_MILESTONES')
            && str_contains($ops, '30')
            && str_contains($ops, '15')
            && str_contains($ops, '7')
            && str_contains($cc, 'contract_milestones')
            && str_contains($dash, 'hr_q_contracts_milestones')
        );

        $this->record(
            'Q2 leave pending/upcoming/low balance without H2 rewrite',
            str_contains($ops, 'processLeaveReminders')
            && str_contains($ops, 'leave_pending')
            && str_contains($ops, 'leave_upcoming')
            && str_contains($ops, 'leave_low_balance')
            && !str_contains($ops, 'approveLeave')
            && !str_contains($ops, 'generatePayrollLines')
            && !str_contains($ops, 'class HrLeaveEngine')
        );

        $this->record(
            'Q3 attendance absence/late daily summary',
            str_contains($ops, 'processAttendanceDaily')
            && str_contains($ops, 'attendance_daily')
            && str_contains($ops, 'absent_count')
            && str_contains($ops, 'late_count')
            && str_contains($cc, 'attendance_alerts')
            && str_contains($dash, 'hr_q_task_attendance')
        );

        $this->record(
            'Q4 payroll pending/reconciliation reminders (no payroll/accounting redesign)',
            str_contains($ops, 'processPayrollReminders')
            && str_contains($ops, 'payroll_pending_approval')
            && str_contains($ops, 'payroll_reconciliation_reminder')
            && !str_contains($ops, 'AccountingService')
            && !str_contains($ops, 'postPayroll')
            && !str_contains($ops, 'HrPayrollAccountingAdapter')
        );

        $this->record(
            'Q5 requests/decisions reminders + configurable escalation',
            str_contains($ops, 'processPendingRequests')
            && str_contains($ops, 'processPendingDecisions')
            && str_contains($ops, 'processOverdueApprovalsEscalation')
            && str_contains($ops, 'escalation_days')
            && str_contains($ops, 'request_escalation')
            && str_contains($ops, 'decision_escalation')
            && str_contains($ops, 'settingsFor')
        );

        $this->record(
            'Q6 reuse NotificationService + CronService (no new engines)',
            str_contains($ops, 'NotificationService')
            && str_contains($cron, 'hr_ops_automation')
            && str_contains($cron, 'HrOpsAutomationService')
            && !str_contains($ops, 'ApprovalEngine2')
            && !str_contains($ops, 'NotificationEngine2')
            && !str_contains($ops, 'WorkflowEngine2')
            && is_file(RATEB_ROOT . '/app/services/ApprovalOversightService.php')
            && is_file(RATEB_ROOT . '/app/services/HrApprovalMatrixService.php')
            && str_contains($notify, 'function notifyCompany')
        );

        $this->record(
            'Q7 idempotency via unique reminder ledger',
            str_contains($ops, 'claimReminder')
            && str_contains($ops, 'uq_hr_ops_reminder') === false // constraint in SQL
            && str_contains($mig, 'UNIQUE KEY uq_hr_ops_reminder')
            && str_contains($ops, 'period_key')
            && str_contains($ops, 'return false') // duplicate claim
        );

        $this->record(
            'Q8 audit automation actions + security company scope',
            str_contains($ops, 'AuditService')
            && str_contains($ops, 'hr_ops_')
            && str_contains($ops, 'company_id = :cid')
            && str_contains($ops, 'TenantContext::setCompanyId')
            && !preg_match('/\$_(GET|POST)\s*\[\s*[\'"]company_id[\'"]/', $ops)
        );

        $this->record(
            'Q9 Command Center overdue/contracts/attendance/tasks + B–P runners',
            str_contains($cc, 'overdue_approvals')
            && str_contains($cc, 'hr_tasks')
            && str_contains($dash, 'overdueApprovals')
            && str_contains($dash, 'hrTasks')
            && (
                is_file(RATEB_ROOT . '/docs/hr/HR-PHASE-Q-OPERATIONS-AUTOMATION-CERTIFICATION.md')
                || is_file(dirname(RATEB_ROOT) . '/docs/hr/HR-PHASE-Q-OPERATIONS-AUTOMATION-CERTIFICATION.md')
            )
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-p-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-o-tests.php')
            && is_file(RATEB_ROOT . '/tests/hr/run-hr-phase-b-security-tests.php')
            && str_contains($oversight, 'listPending')
            && str_contains($matrix, 'canActorDecide')
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
