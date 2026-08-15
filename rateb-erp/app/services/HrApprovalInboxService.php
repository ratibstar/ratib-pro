<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use PDO;

/**
 * Phase F + J — HR Approval Inbox aggregator + actionable decide (company).
 *
 * Reuses ApprovalOversightService for pending SoT (listPending) and decide (process).
 * Matrix gate remains inside Oversight → HrApprovalMatrixService.
 * Does NOT create a third approval engine or second HR workflow service.
 * Does NOT revive blocked company hr/{id}/approve routes.
 * Payroll remains view-only in this inbox (approve via platform Oversight / payroll flow).
 */
final class HrApprovalInboxService
{
    /** @var list<string> */
    public const HR_SOURCE_KEYS = ['hr_leave', 'hr_permission', 'hr_request', 'hr_payroll', 'hr_decision'];

    /** Sources that may be decided from the company inbox (Phase J + M). */
    /** @var list<string> */
    public const ACTIONABLE_SOURCES = ['hr_leave', 'hr_permission', 'hr_request', 'hr_decision'];

    /** @var array<string, string> source_key => inbox type */
    public const TYPE_BY_SOURCE = [
        'hr_leave' => 'leave',
        'hr_permission' => 'permission',
        'hr_request' => 'request',
        'hr_payroll' => 'payroll',
        'hr_decision' => 'decision',
    ];

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   counts: array<string, int>,
     *   deferred: list<string>
     * }
     */
    public function inbox(int $companyId, ?string $typeFilter = null, int $limit = 200, ?int $actorUserId = null): array
    {
        $counts = [
            'leave' => 0,
            'permission' => 0,
            'request' => 0,
            'payroll' => 0,
            'decision' => 0,
            'expense' => 0,
            'total' => 0,
        ];
        $deferred = [
            'expense' => 'HR Expenses pending queue not present — accounting vouchers stay in Accounting oversight.',
        ];

        if ($companyId < 1) {
            return [
                'items' => [],
                'counts' => $counts,
                'deferred' => array_values($deferred),
            ];
        }

        if ($actorUserId === null) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }

        $oversight = new ApprovalOversightService();
        $matrix = new HrApprovalMatrixService();
        // Category filter 'hr' already scopes to HR sources inside ApprovalOversightService.
        $raw = $oversight->listPending($companyId, 'hr', max(50, min(500, $limit)));
        $items = [];
        foreach ($raw as $row) {
            $sourceKey = (string) ($row['source_key'] ?? '');
            if (!in_array($sourceKey, self::HR_SOURCE_KEYS, true)) {
                continue;
            }
            // Defense: never leak another company.
            if ((int) ($row['company_id'] ?? 0) !== $companyId) {
                continue;
            }
            $item = $this->normalize($row, $companyId, $actorUserId, $matrix);
            $type = (string) ($item['type'] ?? '');
            if ($typeFilter !== null && $typeFilter !== '' && $typeFilter !== 'all' && $type !== $typeFilter) {
                continue;
            }
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            $items[] = $item;
        }
        $counts['total'] = count($items);

        return [
            'items' => $items,
            'counts' => $counts,
            'deferred' => array_values($deferred),
        ];
    }

    /** @return array<string, int> */
    public function counts(int $companyId): array
    {
        return $this->inbox($companyId, null, 500)['counts'];
    }

    /**
     * Company inbox decide — authorizes then calls Oversight::process.
     * Never trusts client company_id / employee_id / approver_id.
     *
     * @return array{outcome:string,stage_advanced:bool,message:string}
     */
    public function decide(
        int $companyId,
        int $actorUserId,
        string $sourceKey,
        int $recordId,
        string $action,
        ?string $comment = null
    ): array {
        $action = $action === 'reject' ? 'reject' : 'approve';
        $sourceKey = trim($sourceKey);
        if ($companyId < 1 || $recordId < 1 || $actorUserId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!in_array($sourceKey, self::ACTIONABLE_SOURCES, true)) {
            // Payroll and unknown sources are not actionable here.
            throw new \RuntimeException(__('hr_inbox_payroll_not_actionable'));
        }

        $recordCompanyId = $this->resolveRecordCompanyId($sourceKey, $recordId);
        if ($recordCompanyId < 1 || $recordCompanyId !== $companyId) {
            // Cross-company or missing — same opaque failure.
            throw new \RuntimeException(__('access_denied'));
        }

        $matrix = new HrApprovalMatrixService();
        if (!$matrix->canActorDecide($sourceKey, $recordId, $companyId, $actorUserId, $action)) {
            throw new \RuntimeException(__('access_denied'));
        }

        (new ApprovalOversightService())->process($sourceKey, $recordId, $companyId, $action);

        $ctx = $matrix->decisionContext($sourceKey, $recordId, $companyId);
        // After finalize, progress may be completed/rejected — stage_advanced if still pending.
        $stillPending = $this->isRecordStillPending($sourceKey, $recordId, $companyId);
        $stageAdvanced = $action === 'approve' && $stillPending;

        $payload = [
            'source_key' => $sourceKey,
            'company_id' => $companyId,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            'stage_advanced' => $stageAdvanced,
            'channel' => 'hr_approvals_inbox',
        ];
        (new AuditService())->log(
            $action === 'approve' ? 'hr_inbox_approve' : 'hr_inbox_reject',
            'hr_approval_inbox',
            $recordId,
            $payload
        );

        return [
            'outcome' => $stageAdvanced ? 'stage_advanced' : 'finalized',
            'stage_advanced' => $stageAdvanced,
            'message' => $stageAdvanced
                ? __('hr_inbox_stage_advanced')
                : ($action === 'approve' ? __('hr_inbox_approved') : __('hr_inbox_rejected')),
            'context' => $ctx,
        ];
    }

    /**
     * Normalize oversight row → ApprovalItem (display DTO).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function normalize(
        array $row,
        int $companyId = 0,
        int $actorUserId = 0,
        ?HrApprovalMatrixService $matrix = null
    ): array {
        $sourceKey = (string) ($row['source_key'] ?? '');
        $type = self::TYPE_BY_SOURCE[$sourceKey] ?? 'unknown';
        $recordId = (int) ($row['record_id'] ?? $row['entity_id'] ?? 0);
        $submitted = (string) ($row['submitted_at'] ?? '');
        $ageHours = null;
        if ($submitted !== '') {
            $ts = strtotime($submitted);
            if ($ts !== false) {
                $ageHours = max(0, (int) floor((time() - $ts) / 3600));
            }
        }

        $actionable = in_array($sourceKey, self::ACTIONABLE_SOURCES, true);
        $canReject = $actionable && (bool) ($row['can_reject'] ?? true);
        $matrix ??= new HrApprovalMatrixService();
        $ctx = $actionable && $companyId > 0 && $recordId > 0
            ? $matrix->decisionContext($sourceKey, $recordId, $companyId)
            : null;
        $canAct = false;
        if ($actionable && $companyId > 0 && $recordId > 0 && $actorUserId > 0) {
            $canAct = $matrix->canActorDecide($sourceKey, $recordId, $companyId, $actorUserId, 'approve');
        }

        $subject = $this->resolveSubject($sourceKey, $recordId, $companyId > 0 ? $companyId : (int) ($row['company_id'] ?? 0));

        return [
            'type' => $type,
            'source_key' => $sourceKey,
            'id' => $recordId,
            'company_id' => (int) ($row['company_id'] ?? $companyId),
            'title' => (string) ($row['type_label'] ?? $sourceKey),
            'reference' => (string) ($row['reference'] ?? ''),
            'requester' => (string) ($row['company_name'] ?? ''),
            'employee_id' => (int) ($subject['employee_id'] ?? 0),
            'employee_name' => (string) ($subject['employee_name'] ?? ''),
            'employee_code' => (string) ($subject['employee_code'] ?? ''),
            'summary' => (string) ($subject['summary'] ?? ''),
            'created_at' => $submitted,
            'age_hours' => $ageHours,
            'current_status' => 'pending',
            'display_status' => 'pending',
            'required_action' => $actionable ? 'company_or_oversight_approve' : 'oversight_approve',
            'allowed_action' => $canAct
                ? 'approve_reject_in_company_inbox'
                : ($actionable ? 'view_only_awaiting_authorized_actor' : 'view_only_in_company_inbox'),
            'can_act' => $canAct,
            'can_approve' => $canAct,
            'can_reject' => $canAct && $canReject,
            'actionable' => $actionable,
            'stage_name' => $ctx['stage_name'] ?? null,
            'stage_order' => $ctx['current_stage_order'] ?? null,
            'max_stage_order' => $ctx['max_stage_order'] ?? null,
            'approver_type' => $ctx['approver_type'] ?? null,
            'approver_reference' => $ctx['approver_reference'] ?? null,
            'last_actor_user_id' => $ctx['last_actor_user_id'] ?? null,
            'last_action_at' => $ctx['last_action_at'] ?? null,
            'next_stage_name' => $ctx['next_stage_name'] ?? null,
            'next_outcome' => $ctx['next_outcome'] ?? ($actionable ? 'domain_finalize' : null),
            'has_matrix' => (bool) ($ctx['has_matrix'] ?? false),
            'progress_status' => (string) ($ctx['progress_status'] ?? 'pending'),
            'pending_or_final' => in_array((string) ($ctx['progress_status'] ?? ''), ['completed', 'rejected'], true)
                ? 'final'
                : 'pending',
            'stages_history' => is_array($ctx['stages_history'] ?? null) ? $ctx['stages_history'] : [],
            'priority' => null,
            'amount' => null,
            'source_url' => (string) ($row['view_url'] ?? ''),
            'queue_url' => (string) ($row['queue_url'] ?? ''),
            'source_module' => 'hr',
            'oversight_url' => rateb_url('admin/oversight/hr-approvals'),
        ];
    }

    private function resolveRecordCompanyId(string $sourceKey, int $recordId): int
    {
        $table = match ($sourceKey) {
            'hr_leave' => 'rateb_leave_requests',
            'hr_permission' => 'rateb_hr_permission_requests',
            'hr_request' => 'rateb_hr_employee_requests',
            'hr_payroll' => 'rateb_payroll_periods',
            'hr_decision' => 'rateb_hr_decisions',
            default => '',
        };
        if ($table === '' || $recordId < 1) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT company_id FROM {$table} WHERE id = :id LIMIT 1"
            );
            $stmt->execute(['id' => $recordId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($row['company_id'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function isRecordStillPending(string $sourceKey, int $recordId, int $companyId): bool
    {
        $table = match ($sourceKey) {
            'hr_leave' => 'rateb_leave_requests',
            'hr_permission' => 'rateb_hr_permission_requests',
            'hr_request' => 'rateb_hr_employee_requests',
            'hr_decision' => 'rateb_hr_decisions',
            default => '',
        };
        if ($table === '') {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT status FROM {$table} WHERE id = :id AND company_id = :cid LIMIT 1"
            );
            $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return strtolower((string) ($row['status'] ?? '')) === 'pending';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{employee_id:int,employee_name:string,employee_code:string,summary:string}
     */
    private function resolveSubject(string $sourceKey, int $recordId, int $companyId): array
    {
        $empty = ['employee_id' => 0, 'employee_name' => '', 'employee_code' => '', 'summary' => ''];
        if ($recordId < 1 || $companyId < 1) {
            return $empty;
        }
        try {
            $db = Database::connection();
            if ($sourceKey === 'hr_leave') {
                $stmt = $db->prepare(
                    "SELECT e.id AS employee_id, e.name, e.employee_code, lr.start_date, lr.end_date, lr.days, lt.name AS leave_type
                     FROM rateb_leave_requests lr
                     JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
                     LEFT JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id AND lt.company_id = lr.company_id
                     WHERE lr.id = :id AND lr.company_id = :cid LIMIT 1"
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return $empty;
                }
                return [
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'employee_name' => (string) ($row['name'] ?? ''),
                    'employee_code' => (string) ($row['employee_code'] ?? ''),
                    'summary' => trim(
                        (string) ($row['leave_type'] ?? '') . ' · '
                        . (string) ($row['start_date'] ?? '') . ' → ' . (string) ($row['end_date'] ?? '')
                        . ' (' . (string) ($row['days'] ?? '') . ')'
                    ),
                ];
            }
            if ($sourceKey === 'hr_permission') {
                $stmt = $db->prepare(
                    "SELECT e.id AS employee_id, e.name, e.employee_code, p.permission_date, p.time_from, p.time_to
                     FROM rateb_hr_permission_requests p
                     JOIN rateb_employees e ON e.id = p.employee_id AND e.company_id = p.company_id
                     WHERE p.id = :id AND p.company_id = :cid LIMIT 1"
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return $empty;
                }
                return [
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'employee_name' => (string) ($row['name'] ?? ''),
                    'employee_code' => (string) ($row['employee_code'] ?? ''),
                    'summary' => trim(
                        (string) ($row['permission_date'] ?? '') . ' '
                        . (string) ($row['time_from'] ?? '') . '-' . (string) ($row['time_to'] ?? '')
                    ),
                ];
            }
            if ($sourceKey === 'hr_request') {
                $stmt = $db->prepare(
                    "SELECT e.id AS employee_id, e.name, e.employee_code, r.request_type, r.request_date
                     FROM rateb_hr_employee_requests r
                     JOIN rateb_employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                     WHERE r.id = :id AND r.company_id = :cid LIMIT 1"
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return $empty;
                }
                return [
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'employee_name' => (string) ($row['name'] ?? ''),
                    'employee_code' => (string) ($row['employee_code'] ?? ''),
                    'summary' => trim(
                        (string) ($row['request_type'] ?? '') . ' · ' . (string) ($row['request_date'] ?? '')
                    ),
                ];
            }
            if ($sourceKey === 'hr_payroll') {
                $stmt = $db->prepare(
                    'SELECT period_year, period_month, status FROM rateb_payroll_periods
                     WHERE id = :id AND company_id = :cid LIMIT 1'
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return $empty;
                }
                return [
                    'employee_id' => 0,
                    'employee_name' => '',
                    'employee_code' => '',
                    'summary' => sprintf(
                        '%04d-%02d · %s',
                        (int) ($row['period_year'] ?? 0),
                        (int) ($row['period_month'] ?? 0),
                        (string) ($row['status'] ?? '')
                    ),
                ];
            }
            if ($sourceKey === 'hr_decision') {
                $stmt = $db->prepare(
                    "SELECT e.id AS employee_id, e.name, e.employee_code, d.decision_type, d.decision_no, d.effective_date
                     FROM rateb_hr_decisions d
                     JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id
                     WHERE d.id = :id AND d.company_id = :cid LIMIT 1"
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return $empty;
                }
                return [
                    'employee_id' => (int) ($row['employee_id'] ?? 0),
                    'employee_name' => (string) ($row['name'] ?? ''),
                    'employee_code' => (string) ($row['employee_code'] ?? ''),
                    'summary' => trim(
                        (string) ($row['decision_no'] ?? '') . ' · '
                        . (string) ($row['decision_type'] ?? '') . ' · '
                        . (string) ($row['effective_date'] ?? '')
                    ),
                ];
            }
        } catch (\Throwable $e) {
            return $empty;
        }

        return $empty;
    }
}
