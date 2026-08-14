<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\LeaveRequest;
use Rateb\App\Models\PayrollPeriod;

/**
 * Phase N — HR Command Center (read aggregation only).
 *
 * Does not mutate Employee / Payroll / Leave / Approval engines.
 * All queries company-scoped and LIMIT-bounded (no N+1 employee loops).
 */
final class HrCommandCenterService
{
    public const SEARCH_LIMIT = 15;
    public const LIST_LIMIT = 8;
    public const CONTRACT_ALERT_DAYS = 30;
    public const UPCOMING_LEAVE_DAYS = 14;

    /**
     * Full dashboard payload for Admin HR home.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $companyId, int $actorUserId = 0): array
    {
        if ($companyId < 1) {
            return $this->emptyPayload();
        }

        $stats = $this->workforceStats($companyId);
        $inbox = (new HrApprovalInboxService())->counts($companyId);
        $pendingDecisions = $this->countPendingDecisions($companyId);

        return [
            'stats' => $stats,
            'inbox' => $inbox,
            'approval_center' => [
                'total' => (int) ($inbox['total'] ?? 0),
                'leave' => (int) ($inbox['leave'] ?? 0),
                'permission' => (int) ($inbox['permission'] ?? 0),
                'request' => (int) ($inbox['request'] ?? 0),
                'decision' => (int) ($inbox['decision'] ?? 0),
                'payroll' => (int) ($inbox['payroll'] ?? 0),
            ],
            'pending_decisions' => $pendingDecisions,
            'contracts_expiring' => $this->contractsExpiringSoon($companyId, self::CONTRACT_ALERT_DAYS, self::LIST_LIMIT),
            'contracts_expiring_count' => $this->countContractsExpiringSoon($companyId, self::CONTRACT_ALERT_DAYS),
            'recent_payrolls' => $this->recentPayrolls($companyId, 5),
            'recent_requests' => $this->recentRequests($companyId, self::LIST_LIMIT),
            'recent_decisions' => $this->recentDecisions($companyId, self::LIST_LIMIT),
            'upcoming_leaves' => $this->upcomingLeaves($companyId, self::UPCOMING_LEAVE_DAYS, self::LIST_LIMIT),
            'alerts' => $this->buildAlerts($companyId, $actorUserId, $inbox, $pendingDecisions),
            'quick_actions' => $this->quickActions(),
            'hub_links' => $this->employee360HubLinks(),
        ];
    }

    /**
     * Bounded employee lookup by name / employee_code (company scoped).
     *
     * @return list<array{id:int,name:string,employee_code:string,status:string,url:string}>
     */
    public function searchEmployees(int $companyId, string $q, int $limit = self::SEARCH_LIMIT): array
    {
        if ($companyId < 1) {
            return [];
        }
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 1) {
            return [];
        }
        $limit = max(1, min(self::SEARCH_LIMIT, $limit));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $stmt = Database::connection()->prepare(
            "SELECT id, name, employee_code, status
             FROM rateb_employees
             WHERE company_id = :cid
               AND (name LIKE :q1 OR employee_code LIKE :q2)
             ORDER BY
               CASE WHEN employee_code = :exact THEN 0
                    WHEN employee_code LIKE :prefix THEN 1
                    ELSE 2 END,
               name ASC
             LIMIT {$limit}"
        );
        $stmt->execute([
            'cid' => $companyId,
            'q1' => $like,
            'q2' => $like,
            'exact' => $q,
            'prefix' => $q . '%',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? ''),
                'employee_code' => (string) ($row['employee_code'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'url' => rateb_url(rateb_app_route('hr/employees/' . $id)),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function workforceStats(int $companyId): array
    {
        if ($companyId < 1) {
            return [
                'employees' => 0,
                'active' => 0,
                'present_today' => 0,
                'absent_today' => 0,
                'late_today' => 0,
                'on_leave_today' => 0,
                'pending_leaves' => 0,
                'draft_payrolls' => 0,
            ];
        }

        // Keep legacy keys compatible with HrService::dashboardStats consumers.
        $base = (new HrService())->dashboardStats($companyId);
        $today = date('Y-m-d');
        $att = (new AttendanceRecord())->queryOne(
            "SELECT
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_only,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) AS on_leave
             FROM rateb_attendance_records
             WHERE company_id = :cid AND attendance_date = :d",
            ['cid' => $companyId, 'd' => $today]
        );

        return [
            'employees' => (int) ($base['employees'] ?? 0),
            'active' => (int) ($base['active'] ?? 0),
            'present_today' => (int) ($att['present_only'] ?? 0) + (int) ($att['late'] ?? 0),
            'absent_today' => (int) ($base['absent_today'] ?? 0),
            'late_today' => (int) ($att['late'] ?? 0),
            'on_leave_today' => (int) ($att['on_leave'] ?? 0),
            'pending_leaves' => (int) ($base['pending_leaves'] ?? 0),
            'draft_payrolls' => (int) ($base['draft_payrolls'] ?? 0),
        ];
    }

    /**
     * @return list<array{id:string,label:string,route:string,icon:string}>
     */
    public function quickActions(): array
    {
        return [
            ['id' => 'new_employee', 'label' => 'hr_cc_qa_new_employee', 'route' => 'hr/employees/create', 'icon' => 'fa-user-plus'],
            ['id' => 'leave', 'label' => 'hr_cc_qa_leave', 'route' => 'hr/leaves/create', 'icon' => 'fa-plane'],
            ['id' => 'letter', 'label' => 'hr_cc_qa_letter', 'route' => 'hr/requests/create', 'icon' => 'fa-file-pdf'],
            ['id' => 'decision', 'label' => 'hr_cc_qa_decision', 'route' => 'hr/decisions/create', 'icon' => 'fa-gavel'],
            ['id' => 'disciplinary', 'label' => 'hr_cc_qa_disciplinary', 'route' => 'hr/disciplinary/create', 'icon' => 'fa-triangle-exclamation'],
            ['id' => 'contract', 'label' => 'hr_cc_qa_contract', 'route' => 'hr/employment-contracts', 'icon' => 'fa-file-signature'],
            ['id' => 'inbox', 'label' => 'hr_cc_qa_inbox', 'route' => 'hr/approvals-inbox', 'icon' => 'fa-inbox'],
        ];
    }

    /**
     * Employee 360 hub tab keys (for dashboard + 360 shell).
     *
     * @return list<array{tab:string,label:string,icon:string}>
     */
    public function employee360HubLinks(): array
    {
        return [
            ['tab' => 'employment', 'label' => 'hr_360_tab_employment', 'icon' => 'fa-id-card'],
            ['tab' => 'salary', 'label' => 'hr_360_tab_salary', 'icon' => 'fa-coins'],
            ['tab' => 'attendance', 'label' => 'hr_360_tab_attendance', 'icon' => 'fa-clock'],
            ['tab' => 'leaves', 'label' => 'hr_360_tab_leaves', 'icon' => 'fa-plane'],
            ['tab' => 'requests', 'label' => 'hr_360_tab_requests', 'icon' => 'fa-file-lines'],
            ['tab' => 'letters', 'label' => 'hr_360_tab_letters', 'icon' => 'fa-file-pdf'],
            ['tab' => 'payroll', 'label' => 'hr_360_tab_payroll', 'icon' => 'fa-credit-card'],
            ['tab' => 'documents', 'label' => 'hr_360_tab_documents', 'icon' => 'fa-folder-open'],
            ['tab' => 'decisions', 'label' => 'hr_360_tab_decisions', 'icon' => 'fa-gavel'],
            ['tab' => 'violations', 'label' => 'hr_360_tab_violations', 'icon' => 'fa-triangle-exclamation'],
            ['tab' => 'timeline', 'label' => 'hr_360_tab_timeline', 'icon' => 'fa-timeline'],
        ];
    }

    /**
     * @param array<string, int> $inbox
     * @return list<array<string, mixed>>
     */
    private function buildAlerts(int $companyId, int $actorUserId, array $inbox, int $pendingDecisions): array
    {
        $alerts = [];
        $pendingTotal = (int) ($inbox['total'] ?? 0);
        if ($pendingTotal > 0) {
            $alerts[] = [
                'type' => 'warning',
                'code' => 'pending_approvals',
                'title' => __('hr_cc_alert_pending_approvals'),
                'message' => __('hr_cc_alert_pending_approvals_body', ['count' => $pendingTotal]),
                'url' => rateb_url(rateb_app_route('hr/approvals-inbox')),
            ];
        }
        if ($pendingDecisions > 0) {
            $alerts[] = [
                'type' => 'warning',
                'code' => 'pending_decisions',
                'title' => __('hr_cc_alert_pending_decisions'),
                'message' => __('hr_cc_alert_pending_decisions_body', ['count' => $pendingDecisions]),
                'url' => rateb_url(rateb_app_route('hr/approvals-inbox')) . '?type=decision',
            ];
        }
        $expiring = $this->countContractsExpiringSoon($companyId, self::CONTRACT_ALERT_DAYS);
        if ($expiring > 0) {
            $alerts[] = [
                'type' => 'info',
                'code' => 'contracts_expiring',
                'title' => __('hr_cc_alert_contracts_expiring'),
                'message' => __('hr_cc_alert_contracts_expiring_body', ['count' => $expiring]),
                'url' => rateb_url(rateb_app_route('hr/employment-contracts')),
            ];
        }
        $upcoming = count($this->upcomingLeaves($companyId, self::UPCOMING_LEAVE_DAYS, 1));
        if ($upcoming > 0) {
            $alerts[] = [
                'type' => 'info',
                'code' => 'upcoming_leaves',
                'title' => __('hr_cc_alert_upcoming_leaves'),
                'message' => __('hr_cc_alert_upcoming_leaves_body'),
                'url' => rateb_url(rateb_app_route('hr/leaves')),
            ];
        }

        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }
        if ($actorUserId > 0) {
            $notes = (new NotificationService())->listRecentForUser($actorUserId, $companyId, 5);
            foreach ($notes as $n) {
                $alerts[] = [
                    'type' => (string) ($n['type'] ?? 'info'),
                    'code' => 'notification',
                    'title' => (string) ($n['title'] ?? ''),
                    'message' => (string) ($n['message'] ?? ''),
                    'url' => null,
                    'trigger_type' => (string) ($n['trigger_type'] ?? ''),
                    'created_at' => (string) ($n['created_at'] ?? ''),
                ];
            }
        }

        return array_slice($alerts, 0, 12);
    }

    private function countPendingDecisions(int $companyId): int
    {
        if (!$this->tableExists('rateb_hr_decisions')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_hr_decisions
                 WHERE company_id = :cid AND status = 'pending'"
            );
            $stmt->execute(['cid' => $companyId]);

            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countContractsExpiringSoon(int $companyId, int $days): int
    {
        if (!$this->tableExists('rateb_hr_employment_contracts')) {
            return 0;
        }
        $days = max(1, min(90, $days));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_hr_employment_contracts
                 WHERE company_id = :cid
                   AND status = 'active'
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

    /**
     * @return list<array<string, mixed>>
     */
    private function contractsExpiringSoon(int $companyId, int $days, int $limit): array
    {
        if (!$this->tableExists('rateb_hr_employment_contracts')) {
            return [];
        }
        $days = max(1, min(90, $days));
        $limit = max(1, min(20, $limit));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT c.id, c.contract_no, c.end_date, c.employee_id, e.name AS employee_name, e.employee_code
                 FROM rateb_hr_employment_contracts c
                 JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
                 WHERE c.company_id = :cid
                   AND c.status = 'active'
                   AND c.end_date IS NOT NULL
                   AND c.end_date >= CURDATE()
                   AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY)
                 ORDER BY c.end_date ASC
                 LIMIT {$limit}"
            );
            $stmt->execute(['cid' => $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPayrolls(int $companyId, int $limit): array
    {
        $limit = max(1, min(10, $limit));
        try {
            return (new PayrollPeriod())->query(
                "SELECT id, period_year, period_month, status, created_at
                 FROM rateb_payroll_periods
                 WHERE company_id = :cid
                 ORDER BY period_year DESC, period_month DESC, id DESC
                 LIMIT {$limit}",
                ['cid' => $companyId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentRequests(int $companyId, int $limit): array
    {
        if (!$this->tableExists('rateb_hr_employee_requests')) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT r.id, r.request_no, r.request_type, r.status, r.created_at,
                        e.name AS employee_name, e.employee_code
                 FROM rateb_hr_employee_requests r
                 JOIN rateb_employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                 WHERE r.company_id = :cid
                 ORDER BY r.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute(['cid' => $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentDecisions(int $companyId, int $limit): array
    {
        if (!$this->tableExists('rateb_hr_decisions')) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT d.id, d.decision_no, d.decision_type, d.status, d.effective_date, d.created_at,
                        e.name AS employee_name, e.employee_code
                 FROM rateb_hr_decisions d
                 JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id
                 WHERE d.company_id = :cid
                 ORDER BY d.id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute(['cid' => $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingLeaves(int $companyId, int $withinDays, int $limit): array
    {
        $withinDays = max(1, min(60, $withinDays));
        $limit = max(1, min(20, $limit));
        try {
            return (new LeaveRequest())->query(
                "SELECT lr.id, lr.start_date, lr.end_date, lr.days, lr.status,
                        e.name AS employee_name, e.employee_code
                 FROM rateb_leave_requests lr
                 JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
                 WHERE lr.company_id = :cid
                   AND lr.status = 'approved'
                   AND lr.start_date >= CURDATE()
                   AND lr.start_date <= DATE_ADD(CURDATE(), INTERVAL {$withinDays} DAY)
                 ORDER BY lr.start_date ASC
                 LIMIT {$limit}",
                ['cid' => $companyId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Database::tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'stats' => $this->workforceStats(0),
            'inbox' => ['total' => 0, 'leave' => 0, 'permission' => 0, 'request' => 0, 'decision' => 0, 'payroll' => 0],
            'approval_center' => [
                'total' => 0, 'leave' => 0, 'permission' => 0, 'request' => 0, 'decision' => 0, 'payroll' => 0,
            ],
            'pending_decisions' => 0,
            'contracts_expiring' => [],
            'contracts_expiring_count' => 0,
            'recent_payrolls' => [],
            'recent_requests' => [],
            'recent_decisions' => [],
            'upcoming_leaves' => [],
            'alerts' => [],
            'quick_actions' => $this->quickActions(),
            'hub_links' => $this->employee360HubLinks(),
        ];
    }
}
