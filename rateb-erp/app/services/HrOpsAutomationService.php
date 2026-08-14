<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;

/**
 * Phase Q — HR Operations Automation.
 *
 * Uses NotificationService + existing cron. Does NOT create a workflow/notification engine.
 * Does NOT mutate leave/payroll/approval decision logic — reminders and Command Center reads only.
 * Idempotency via rateb_hr_ops_reminder_ledger unique key.
 */
final class HrOpsAutomationService
{
    public const DEFAULT_ESCALATION_DAYS = 3;
    public const DEFAULT_LOW_BALANCE_DAYS = 3.0;
    public const DEFAULT_UPCOMING_LEAVE_DAYS = 7;
    /** @var list<int> */
    public const DEFAULT_CONTRACT_MILESTONES = [30, 15, 7];

    public const PER_COMPANY_LIMIT = 40;

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_ops_reminder_ledger');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Cron entry — company-scoped loop, budget-capped.
     *
     * @return array<string, int>
     */
    public function runAll(): array
    {
        $stats = [
            'companies' => 0,
            'contracts' => 0,
            'leaves' => 0,
            'attendance' => 0,
            'payroll' => 0,
            'requests' => 0,
            'decisions' => 0,
            'escalations' => 0,
            'skipped_no_schema' => 0,
        ];
        if (!$this->schemaReady()) {
            $stats['skipped_no_schema'] = 1;

            return $stats;
        }

        $companies = (new Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id LIMIT 500"
        );
        foreach (is_array($companies) ? $companies : [] as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            TenantContext::setCompanyId($cid);
            $stats['companies']++;
            $result = $this->runForCompany($cid);
            foreach (['contracts', 'leaves', 'attendance', 'payroll', 'requests', 'decisions', 'escalations'] as $k) {
                $stats[$k] += (int) ($result[$k] ?? 0);
            }
        }
        TenantContext::setCompanyId(null);

        (new AuditService())->log('hr_ops_automation_run', 'hr_ops_automation', 0, [
            'stats' => $stats,
            'channel' => 'erp-cron',
        ]);

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function runForCompany(int $companyId): array
    {
        $out = [
            'contracts' => 0,
            'leaves' => 0,
            'attendance' => 0,
            'payroll' => 0,
            'requests' => 0,
            'decisions' => 0,
            'escalations' => 0,
        ];
        if ($companyId < 1 || !$this->schemaReady()) {
            return $out;
        }
        $settings = $this->settingsFor($companyId);
        $out['contracts'] = $this->processContractMilestones($companyId, $settings);
        $leave = $this->processLeaveReminders($companyId, $settings);
        $out['leaves'] = (int) ($leave['sent'] ?? 0);
        $out['attendance'] = $this->processAttendanceDaily($companyId);
        $out['payroll'] = $this->processPayrollReminders($companyId);
        $req = $this->processPendingRequests($companyId, $settings);
        $out['requests'] = (int) ($req['reminders'] ?? 0);
        $out['escalations'] += (int) ($req['escalations'] ?? 0);
        $dec = $this->processPendingDecisions($companyId, $settings);
        $out['decisions'] = (int) ($dec['reminders'] ?? 0);
        $out['escalations'] += (int) ($dec['escalations'] ?? 0);
        $out['escalations'] += $this->processOverdueApprovalsEscalation($companyId, $settings);

        return $out;
    }

    /**
     * Command Center read model — overdue / contracts / attendance / tasks.
     *
     * @return array{
     *   overdue_approvals:int,
     *   contracts:array{d30:int,d15:int,d7:int},
     *   attendance:array{absent:int,late:int,date:string},
     *   tasks:list<array<string,mixed>>,
     *   escalation_days:int
     * }
     */
    public function commandCenterOps(int $companyId): array
    {
        $settings = $this->settingsFor($companyId);
        $escalation = (int) $settings['escalation_days'];
        $attendance = $this->attendanceTodayCounts($companyId);
        $contracts = [
            'd30' => $this->countContractsWithin($companyId, 30),
            'd15' => $this->countContractsWithin($companyId, 15),
            'd7' => $this->countContractsWithin($companyId, 7),
        ];
        $overdue = $this->countOverdueApprovals($companyId, $escalation);
        $tasks = $this->buildHrTasks($companyId, $settings, $attendance, $contracts, $overdue);

        return [
            'overdue_approvals' => $overdue,
            'contracts' => $contracts,
            'attendance' => $attendance,
            'tasks' => $tasks,
            'escalation_days' => $escalation,
        ];
    }

    /**
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     */
    private function processContractMilestones(int $companyId, array $settings): int
    {
        if (!Database::tableExists('rateb_hr_employment_contracts')) {
            return 0;
        }
        $sent = 0;
        $milestones = $settings['contract_milestones'];
        rsort($milestones);
        $max = max($milestones);
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, contract_no, end_date, employee_id
                 FROM rateb_hr_employment_contracts
                 WHERE company_id = :cid AND status = 'active'
                   AND end_date IS NOT NULL
                   AND end_date >= CURDATE()
                   AND end_date <= DATE_ADD(CURDATE(), INTERVAL :maxd DAY)
                 ORDER BY end_date ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId, 'maxd' => $max]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $end = (string) ($row['end_date'] ?? '');
                if ($id < 1 || $end === '') {
                    continue;
                }
                $daysLeft = (int) floor((strtotime($end) - strtotime('today')) / 86400);
                foreach ($milestones as $m) {
                    if ($daysLeft > $m) {
                        continue;
                    }
                    $period = 'd' . $m;
                    if (!$this->claimReminder($companyId, 'contract_expiry_' . $period, 'hr_employment_contract', $id, $period)) {
                        continue;
                    }
                    $nid = (new NotificationService())->notifyCompany(
                        $companyId,
                        function_exists('__') ? (string) __('hr_q_contract_expiry_title') : 'Contract expiring',
                        str_replace(
                            [':no', ':date', ':days'],
                            [(string) ($row['contract_no'] ?? ''), $end, (string) $daysLeft],
                            function_exists('__') ? (string) __('hr_q_contract_expiry_body') : 'Contract :no ends :date (:days days).'
                        ),
                        'warning',
                        'hr_ops_contract_expiry_' . $period,
                        'hr_employment_contract',
                        $id
                    );
                    $this->stampNotification($companyId, 'contract_expiry_' . $period, 'hr_employment_contract', $id, $period, $nid);
                    $this->audit($companyId, 'contract_expiry_' . $period, $id, ['days_left' => $daysLeft]);
                    $sent++;
                    break; // one milestone bucket per contract per run
                }
            }
        } catch (\Throwable $e) {
            return $sent;
        }

        return $sent;
    }

    /**
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     * @return array{sent:int}
     */
    private function processLeaveReminders(int $companyId, array $settings): array
    {
        $sent = 0;
        $today = date('Y-m-d');
        $escalation = (int) $settings['escalation_days'];
        $upcomingDays = (int) $settings['upcoming_leave_days'];
        $lowBal = (float) $settings['low_balance_days'];

        // Pending leave reminders (age >= escalation) — does not change H2 leave logic.
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, employee_id, start_date, end_date, status, created_at
                 FROM rateb_leave_requests
                 WHERE company_id = :cid AND status = 'pending'
                   AND created_at <= DATE_SUB(NOW(), INTERVAL :days DAY)
                 ORDER BY id ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId, 'days' => $escalation]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $period = $today;
                if (!$this->claimReminder($companyId, 'leave_pending', 'hr_leave', $id, $period)) {
                    continue;
                }
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    function_exists('__') ? (string) __('hr_q_leave_pending_title') : 'Pending leave reminder',
                    str_replace(
                        [':id', ':start'],
                        [(string) $id, (string) ($row['start_date'] ?? '')],
                        function_exists('__') ? (string) __('hr_q_leave_pending_body') : 'Leave #:id pending since before :start.'
                    ),
                    'warning',
                    'hr_ops_leave_pending',
                    'hr_leave',
                    $id
                );
                $this->stampNotification($companyId, 'leave_pending', 'hr_leave', $id, $period, $nid);
                $this->audit($companyId, 'leave_pending', $id, []);
                $sent++;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Upcoming approved leave.
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, employee_id, start_date, end_date
                 FROM rateb_leave_requests
                 WHERE company_id = :cid AND status = 'approved'
                   AND start_date >= CURDATE()
                   AND start_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                 ORDER BY start_date ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId, 'days' => $upcomingDays]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $period = (string) ($row['start_date'] ?? $today);
                if (!$this->claimReminder($companyId, 'leave_upcoming', 'hr_leave', $id, $period)) {
                    continue;
                }
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    function_exists('__') ? (string) __('hr_q_leave_upcoming_title') : 'Upcoming leave',
                    str_replace(
                        [':start', ':end'],
                        [(string) ($row['start_date'] ?? ''), (string) ($row['end_date'] ?? '')],
                        function_exists('__') ? (string) __('hr_q_leave_upcoming_body') : 'Approved leave :start → :end.'
                    ),
                    'info',
                    'hr_ops_leave_upcoming',
                    'hr_leave',
                    $id
                );
                $this->stampNotification($companyId, 'leave_upcoming', 'hr_leave', $id, $period, $nid);
                $this->audit($companyId, 'leave_upcoming', $id, []);
                $sent++;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Low leave balance (annual-like remaining).
        try {
            $year = (int) date('Y');
            $stmt = Database::connection()->prepare(
                "SELECT lb.id, lb.employee_id, lb.leave_type_id,
                        (lb.entitled_days - lb.used_days) AS remaining
                 FROM rateb_leave_balances lb
                 WHERE lb.company_id = :cid AND lb.balance_year = :yr
                   AND (lb.entitled_days - lb.used_days) <= :low
                   AND (lb.entitled_days - lb.used_days) >= 0
                 ORDER BY remaining ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId, 'yr' => $year, 'low' => $lowBal]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $eid = (int) ($row['employee_id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $period = $year . '-Q';
                if (!$this->claimReminder($companyId, 'leave_low_balance', 'hr_leave_balance', $id, $period)) {
                    continue;
                }
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    function_exists('__') ? (string) __('hr_q_leave_low_balance_title') : 'Low leave balance',
                    str_replace(
                        [':employee_id', ':remaining'],
                        [(string) $eid, (string) ($row['remaining'] ?? '')],
                        function_exists('__') ? (string) __('hr_q_leave_low_balance_body') : 'Employee #:employee_id remaining :remaining days.'
                    ),
                    'warning',
                    'hr_ops_leave_low_balance',
                    'hr_leave_balance',
                    $id
                );
                $this->stampNotification($companyId, 'leave_low_balance', 'hr_leave_balance', $id, $period, $nid);
                $this->audit($companyId, 'leave_low_balance', $id, ['employee_id' => $eid]);
                $sent++;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return ['sent' => $sent];
    }

    private function processAttendanceDaily(int $companyId): int
    {
        $today = date('Y-m-d');
        $counts = $this->attendanceTodayCounts($companyId);
        if ((int) $counts['absent'] < 1 && (int) $counts['late'] < 1) {
            return 0;
        }
        if (!$this->claimReminder($companyId, 'attendance_daily', 'hr_attendance_day', 0, $today)) {
            return 0;
        }
        $nid = (new NotificationService())->notifyCompany(
            $companyId,
            function_exists('__') ? (string) __('hr_q_attendance_daily_title') : 'Daily attendance summary',
            str_replace(
                [':date', ':absent', ':late'],
                [$today, (string) $counts['absent'], (string) $counts['late']],
                function_exists('__') ? (string) __('hr_q_attendance_daily_body') : 'Attendance :date — absent :absent, late :late.'
            ),
            'warning',
            'hr_ops_attendance_daily',
            'hr_attendance_day',
            0
        );
        $this->stampNotification($companyId, 'attendance_daily', 'hr_attendance_day', 0, $today, $nid);
        $this->audit($companyId, 'attendance_daily', 0, $counts);

        return 1;
    }

    private function processPayrollReminders(int $companyId): int
    {
        $sent = 0;
        $monthKey = date('Y-m');
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, period_year, period_month, status
                 FROM rateb_payroll_periods
                 WHERE company_id = :cid AND status IN ('draft','approved')
                 ORDER BY period_year DESC, period_month DESC
                 LIMIT 20"
            );
            $stmt->execute(['cid' => $companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                if ($id < 1) {
                    continue;
                }
                $type = $status === 'draft' ? 'payroll_pending_approval' : 'payroll_reconciliation_reminder';
                $period = $monthKey . '-' . $status;
                if (!$this->claimReminder($companyId, $type, 'hr_payroll', $id, $period)) {
                    continue;
                }
                $title = $status === 'draft'
                    ? (function_exists('__') ? (string) __('hr_q_payroll_pending_title') : 'Payroll pending approval')
                    : (function_exists('__') ? (string) __('hr_q_payroll_reconcile_title') : 'Payroll reconciliation reminder');
                $bodyTpl = $status === 'draft'
                    ? (function_exists('__') ? (string) __('hr_q_payroll_pending_body') : 'Payroll period :ym is draft.')
                    : (function_exists('__') ? (string) __('hr_q_payroll_reconcile_body') : 'Payroll period :ym is approved — reconcile/post when ready.');
                $ym = sprintf('%04d-%02d', (int) ($row['period_year'] ?? 0), (int) ($row['period_month'] ?? 0));
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    $title,
                    str_replace(':ym', $ym, $bodyTpl),
                    'warning',
                    'hr_ops_' . $type,
                    'hr_payroll',
                    $id
                );
                $this->stampNotification($companyId, $type, 'hr_payroll', $id, $period, $nid);
                $this->audit($companyId, $type, $id, ['status' => $status]);
                $sent++;
            }
        } catch (\Throwable $e) {
            return $sent;
        }

        return $sent;
    }

    /**
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     * @return array{reminders:int,escalations:int}
     */
    private function processPendingRequests(int $companyId, array $settings): array
    {
        $reminders = 0;
        $escalations = 0;
        $today = date('Y-m-d');
        $days = (int) $settings['escalation_days'];
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, request_type, status, created_at
                 FROM rateb_hr_employee_requests
                 WHERE company_id = :cid AND status IN ('pending','open')
                 ORDER BY id ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $ageDays = $this->ageDays((string) ($row['created_at'] ?? ''));
                $isEscalation = $ageDays >= $days;
                $rtype = $isEscalation ? 'request_escalation' : 'request_pending';
                $period = $isEscalation ? $today . '-esc' : 'open';
                if (!$isEscalation && $ageDays < 1) {
                    // Allow same-day create notify once via period=open.
                }
                if (!$this->claimReminder($companyId, $rtype, 'hr_request', $id, $period)) {
                    continue;
                }
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    $isEscalation
                        ? (function_exists('__') ? (string) __('hr_q_request_escalation_title') : 'Request escalation')
                        : (function_exists('__') ? (string) __('hr_q_request_pending_title') : 'Pending request'),
                    str_replace(
                        [':id', ':type', ':days'],
                        [(string) $id, (string) ($row['request_type'] ?? ''), (string) $ageDays],
                        $isEscalation
                            ? (function_exists('__') ? (string) __('hr_q_request_escalation_body') : 'Request #:id (:type) overdue :days days.')
                            : (function_exists('__') ? (string) __('hr_q_request_pending_body') : 'Request #:id (:type) still pending.')
                    ),
                    $isEscalation ? 'danger' : 'warning',
                    'hr_ops_' . $rtype,
                    'hr_request',
                    $id
                );
                $this->stampNotification($companyId, $rtype, 'hr_request', $id, $period, $nid);
                $this->audit($companyId, $rtype, $id, ['age_days' => $ageDays]);
                if ($isEscalation) {
                    $escalations++;
                } else {
                    $reminders++;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return ['reminders' => $reminders, 'escalations' => $escalations];
    }

    /**
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     * @return array{reminders:int,escalations:int}
     */
    private function processPendingDecisions(int $companyId, array $settings): array
    {
        $reminders = 0;
        $escalations = 0;
        if (!Database::tableExists('rateb_hr_decisions')) {
            return ['reminders' => 0, 'escalations' => 0];
        }
        $today = date('Y-m-d');
        $days = (int) $settings['escalation_days'];
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, decision_no, decision_type, status, created_at
                 FROM rateb_hr_decisions
                 WHERE company_id = :cid AND status = 'pending'
                 ORDER BY id ASC
                 LIMIT " . self::PER_COMPANY_LIMIT
            );
            $stmt->execute(['cid' => $companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $ageDays = $this->ageDays((string) ($row['created_at'] ?? ''));
                $isEscalation = $ageDays >= $days;
                $rtype = $isEscalation ? 'decision_escalation' : 'decision_pending';
                $period = $isEscalation ? $today . '-esc' : 'open';
                if (!$this->claimReminder($companyId, $rtype, 'hr_decision', $id, $period)) {
                    continue;
                }
                $nid = (new NotificationService())->notifyCompany(
                    $companyId,
                    $isEscalation
                        ? (function_exists('__') ? (string) __('hr_q_decision_escalation_title') : 'Decision escalation')
                        : (function_exists('__') ? (string) __('hr_q_decision_pending_title') : 'Pending decision'),
                    str_replace(
                        [':no', ':days'],
                        [(string) ($row['decision_no'] ?? $id), (string) $ageDays],
                        $isEscalation
                            ? (function_exists('__') ? (string) __('hr_q_decision_escalation_body') : 'Decision :no overdue :days days.')
                            : (function_exists('__') ? (string) __('hr_q_decision_pending_body') : 'Decision :no still pending.')
                    ),
                    $isEscalation ? 'danger' : 'warning',
                    'hr_ops_' . $rtype,
                    'hr_decision',
                    $id
                );
                $this->stampNotification($companyId, $rtype, 'hr_decision', $id, $period, $nid);
                $this->audit($companyId, $rtype, $id, ['age_days' => $ageDays]);
                if ($isEscalation) {
                    $escalations++;
                } else {
                    $reminders++;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return ['reminders' => $reminders, 'escalations' => $escalations];
    }

    /**
     * Approver reminder for overdue HR pending sources (leave/permission) — notify only.
     *
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     */
    private function processOverdueApprovalsEscalation(int $companyId, array $settings): int
    {
        $days = (int) $settings['escalation_days'];
        $today = date('Y-m-d');
        $count = $this->countOverdueApprovals($companyId, $days);
        if ($count < 1) {
            return 0;
        }
        if (!$this->claimReminder($companyId, 'approver_overdue_digest', 'hr_approvals', 0, $today)) {
            return 0;
        }
        $nid = (new NotificationService())->notifyCompany(
            $companyId,
            function_exists('__') ? (string) __('hr_q_approver_overdue_title') : 'Overdue HR approvals',
            str_replace(
                [':count', ':days'],
                [(string) $count, (string) $days],
                function_exists('__') ? (string) __('hr_q_approver_overdue_body') : ':count HR items pending longer than :days days.'
            ),
            'danger',
            'hr_ops_approver_overdue',
            'hr_approvals',
            0
        );
        $this->stampNotification($companyId, 'approver_overdue_digest', 'hr_approvals', 0, $today, $nid);
        $this->audit($companyId, 'approver_overdue_digest', 0, ['count' => $count, 'escalation_days' => $days]);

        return 1;
    }

    /**
     * @return array{absent:int,late:int,date:string}
     */
    private function attendanceTodayCounts(int $companyId): array
    {
        $today = date('Y-m-d');
        $out = ['absent' => 0, 'late' => 0, 'date' => $today];
        if ($companyId < 1) {
            return $out;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count
                 FROM rateb_attendance_records
                 WHERE company_id = :cid AND attendance_date = :d"
            );
            $stmt->execute(['cid' => $companyId, 'd' => $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['absent'] = (int) ($row['absent_count'] ?? 0);
            $out['late'] = (int) ($row['late_count'] ?? 0);
        } catch (\Throwable $e) {
            return $out;
        }

        return $out;
    }

    private function countContractsWithin(int $companyId, int $days): int
    {
        if ($companyId < 1 || !Database::tableExists('rateb_hr_employment_contracts')) {
            return 0;
        }
        $days = max(1, min(90, $days));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_hr_employment_contracts
                 WHERE company_id = :cid AND status = 'active'
                   AND end_date IS NOT NULL
                   AND end_date >= CURDATE()
                   AND end_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY)"
            );
            $stmt->execute(['cid' => $companyId]);

            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countOverdueApprovals(int $companyId, int $escalationDays): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $escalationDays = max(1, min(90, $escalationDays));
        $total = 0;
        $queries = [
            "SELECT COUNT(*) FROM rateb_leave_requests WHERE company_id = :cid AND status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL {$escalationDays} DAY)",
            "SELECT COUNT(*) FROM rateb_hr_permission_requests WHERE company_id = :cid AND status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL {$escalationDays} DAY)",
            "SELECT COUNT(*) FROM rateb_hr_employee_requests WHERE company_id = :cid AND status IN ('pending','open') AND created_at <= DATE_SUB(NOW(), INTERVAL {$escalationDays} DAY)",
        ];
        if (Database::tableExists('rateb_hr_decisions')) {
            $queries[] = "SELECT COUNT(*) FROM rateb_hr_decisions WHERE company_id = :cid AND status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL {$escalationDays} DAY)";
        }
        foreach ($queries as $sql) {
            try {
                $stmt = Database::connection()->prepare($sql);
                $stmt->execute(['cid' => $companyId]);
                $total += (int) ($stmt->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $total;
    }

    /**
     * @param array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>} $settings
     * @param array{absent:int,late:int,date:string} $attendance
     * @param array{d30:int,d15:int,d7:int} $contracts
     * @return list<array<string,mixed>>
     */
    private function buildHrTasks(
        int $companyId,
        array $settings,
        array $attendance,
        array $contracts,
        int $overdue
    ): array {
        $tasks = [];
        if ($overdue > 0) {
            $tasks[] = [
                'code' => 'overdue_approvals',
                'label' => 'hr_q_task_overdue_approvals',
                'count' => $overdue,
                'url' => rateb_url(rateb_app_route('hr/approvals-inbox')),
                'severity' => 'danger',
            ];
        }
        if ((int) ($contracts['d7'] ?? 0) > 0) {
            $tasks[] = [
                'code' => 'contracts_d7',
                'label' => 'hr_q_task_contracts_d7',
                'count' => (int) $contracts['d7'],
                'url' => rateb_url(rateb_app_route('hr/employment-contracts')),
                'severity' => 'warning',
            ];
        } elseif ((int) ($contracts['d15'] ?? 0) > 0) {
            $tasks[] = [
                'code' => 'contracts_d15',
                'label' => 'hr_q_task_contracts_d15',
                'count' => (int) $contracts['d15'],
                'url' => rateb_url(rateb_app_route('hr/employment-contracts')),
                'severity' => 'warning',
            ];
        } elseif ((int) ($contracts['d30'] ?? 0) > 0) {
            $tasks[] = [
                'code' => 'contracts_d30',
                'label' => 'hr_q_task_contracts_d30',
                'count' => (int) $contracts['d30'],
                'url' => rateb_url(rateb_app_route('hr/employment-contracts')),
                'severity' => 'info',
            ];
        }
        if ((int) ($attendance['absent'] ?? 0) > 0 || (int) ($attendance['late'] ?? 0) > 0) {
            $tasks[] = [
                'code' => 'attendance_alerts',
                'label' => 'hr_q_task_attendance',
                'count' => (int) $attendance['absent'] + (int) $attendance['late'],
                'url' => rateb_url(rateb_app_route('hr/attendance')),
                'severity' => 'warning',
                'meta' => $attendance,
            ];
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_leave_requests WHERE company_id = :cid AND status = 'pending'"
            );
            $stmt->execute(['cid' => $companyId]);
            $pendingLeaves = (int) ($stmt->fetchColumn() ?: 0);
            if ($pendingLeaves > 0) {
                $tasks[] = [
                    'code' => 'pending_leaves',
                    'label' => 'hr_q_task_pending_leaves',
                    'count' => $pendingLeaves,
                    'url' => rateb_url(rateb_app_route('hr/leaves')),
                    'severity' => 'warning',
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_payroll_periods WHERE company_id = :cid AND status IN ('draft','approved')"
            );
            $stmt->execute(['cid' => $companyId]);
            $pay = (int) ($stmt->fetchColumn() ?: 0);
            if ($pay > 0) {
                $tasks[] = [
                    'code' => 'payroll_followup',
                    'label' => 'hr_q_task_payroll',
                    'count' => $pay,
                    'url' => rateb_url(rateb_app_route('hr/payroll')),
                    'severity' => 'info',
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return array_slice($tasks, 0, 8);
    }

    /**
     * @return array{escalation_days:int,low_balance_days:float,upcoming_leave_days:int,contract_milestones:list<int>}
     */
    public function settingsFor(int $companyId): array
    {
        $defaults = [
            'escalation_days' => self::DEFAULT_ESCALATION_DAYS,
            'low_balance_days' => self::DEFAULT_LOW_BALANCE_DAYS,
            'upcoming_leave_days' => self::DEFAULT_UPCOMING_LEAVE_DAYS,
            'contract_milestones' => self::DEFAULT_CONTRACT_MILESTONES,
        ];
        if ($companyId < 1 || !Database::tableExists('rateb_hr_ops_automation_settings')) {
            return $defaults;
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT escalation_days, low_balance_days, upcoming_leave_days, contract_milestones
                 FROM rateb_hr_ops_automation_settings WHERE company_id = :cid LIMIT 1'
            );
            $stmt->execute(['cid' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $defaults;
            }
            $milestones = [];
            foreach (explode(',', (string) ($row['contract_milestones'] ?? '30,15,7')) as $p) {
                $n = (int) trim($p);
                if ($n > 0) {
                    $milestones[] = $n;
                }
            }
            if ($milestones === []) {
                $milestones = self::DEFAULT_CONTRACT_MILESTONES;
            }

            return [
                'escalation_days' => max(1, min(90, (int) ($row['escalation_days'] ?? self::DEFAULT_ESCALATION_DAYS))),
                'low_balance_days' => max(0.5, min(30.0, (float) ($row['low_balance_days'] ?? self::DEFAULT_LOW_BALANCE_DAYS))),
                'upcoming_leave_days' => max(1, min(30, (int) ($row['upcoming_leave_days'] ?? self::DEFAULT_UPCOMING_LEAVE_DAYS))),
                'contract_milestones' => $milestones,
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    private function claimReminder(
        int $companyId,
        string $reminderType,
        string $entityType,
        int $entityId,
        string $periodKey
    ): bool {
        if (!$this->schemaReady()) {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO rateb_hr_ops_reminder_ledger
                 (company_id, reminder_type, entity_type, entity_id, period_key, created_at)
                 VALUES (:cid, :rt, :et, :eid, :pk, :ca)'
            );
            $stmt->execute([
                'cid' => $companyId,
                'rt' => mb_substr($reminderType, 0, 64),
                'et' => mb_substr($entityType, 0, 64),
                'eid' => max(0, $entityId),
                'pk' => mb_substr($periodKey, 0, 32),
                'ca' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false; // duplicate unique key = already claimed
        }
    }

    private function stampNotification(
        int $companyId,
        string $reminderType,
        string $entityType,
        int $entityId,
        string $periodKey,
        int $notificationId
    ): void {
        if ($notificationId < 1 || !$this->schemaReady()) {
            return;
        }
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE rateb_hr_ops_reminder_ledger
                 SET notification_id = :nid
                 WHERE company_id = :cid AND reminder_type = :rt AND entity_type = :et
                   AND entity_id = :eid AND period_key = :pk'
            );
            $stmt->execute([
                'nid' => $notificationId,
                'cid' => $companyId,
                'rt' => $reminderType,
                'et' => $entityType,
                'eid' => $entityId,
                'pk' => $periodKey,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @param array<string,mixed> $meta */
    private function audit(int $companyId, string $action, int $entityId, array $meta): void
    {
        try {
            (new AuditService())->log('hr_ops_' . $action, 'hr_ops_automation', $entityId, array_merge([
                'company_id' => $companyId,
            ], $meta));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ageDays(string $createdAt): int
    {
        if ($createdAt === '') {
            return 0;
        }
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return 0;
        }

        return max(0, (int) floor((time() - $ts) / 86400));
    }
}
