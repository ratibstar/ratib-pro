<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Phase F — HR Approval Inbox aggregator (read-only).
 *
 * Reuses ApprovalOversightService as the pending SoT.
 * Does NOT approve/reject and does NOT rewrite module engines.
 *
 * Canonical ApprovalItem shape (in-memory only — no shared table).
 */
final class HrApprovalInboxService
{
    /** @var list<string> */
    public const HR_SOURCE_KEYS = ['hr_leave', 'hr_permission', 'hr_request', 'hr_payroll'];

    /** @var array<string, string> source_key => inbox type */
    public const TYPE_BY_SOURCE = [
        'hr_leave' => 'leave',
        'hr_permission' => 'permission',
        'hr_request' => 'request',
        'hr_payroll' => 'payroll',
    ];

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   counts: array<string, int>,
     *   deferred: list<string>
     * }
     */
    public function inbox(int $companyId, ?string $typeFilter = null, int $limit = 200): array
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
            'decision' => 'HR Decisions module not present in live ops — deferred.',
            'expense' => 'HR Expenses pending queue not present — accounting vouchers stay in Accounting oversight.',
        ];

        if ($companyId < 1) {
            return [
                'items' => [],
                'counts' => $counts,
                'deferred' => array_values($deferred),
            ];
        }

        $oversight = new ApprovalOversightService();
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
            $item = $this->normalize($row);
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
     * Normalize oversight row → ApprovalItem (display DTO).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array
    {
        $sourceKey = (string) ($row['source_key'] ?? '');
        $type = self::TYPE_BY_SOURCE[$sourceKey] ?? 'unknown';
        $submitted = (string) ($row['submitted_at'] ?? '');
        $ageHours = null;
        if ($submitted !== '') {
            $ts = strtotime($submitted);
            if ($ts !== false) {
                $ageHours = max(0, (int) floor((time() - $ts) / 3600));
            }
        }

        return [
            'type' => $type,
            'source_key' => $sourceKey,
            'id' => (int) ($row['record_id'] ?? $row['entity_id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'title' => (string) ($row['type_label'] ?? $sourceKey),
            'reference' => (string) ($row['reference'] ?? ''),
            'requester' => (string) ($row['company_name'] ?? ''),
            'created_at' => $submitted,
            'age_hours' => $ageHours,
            'current_status' => 'pending',
            'display_status' => 'pending',
            'required_action' => 'oversight_approve',
            'allowed_action' => 'view_only_in_company_inbox',
            'can_reject' => (bool) ($row['can_reject'] ?? false),
            'priority' => null,
            'amount' => null,
            'source_url' => (string) ($row['view_url'] ?? ''),
            'queue_url' => (string) ($row['queue_url'] ?? ''),
            'source_module' => 'hr',
            'oversight_url' => rateb_url('admin/oversight/approvals') . '?type=hr',
        ];
    }
}
