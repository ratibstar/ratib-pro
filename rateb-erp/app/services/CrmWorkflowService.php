<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmStatusHistory;

/**
 * Lead lifecycle transitions — sole authority for workflow_status changes.
 * Future Offline Replay (17B) must call transition() — never mutate status directly.
 */
final class CrmWorkflowService
{
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_PROPOSAL = 'proposal';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_ARCHIVED = 'archived';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_CONTACTED,
            self::STATUS_QUALIFIED,
            self::STATUS_PROPOSAL,
            self::STATUS_WON,
            self::STATUS_LOST,
            self::STATUS_ARCHIVED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_NEW => [self::STATUS_CONTACTED, self::STATUS_QUALIFIED, self::STATUS_ARCHIVED],
            self::STATUS_CONTACTED => [self::STATUS_QUALIFIED, self::STATUS_PROPOSAL, self::STATUS_LOST, self::STATUS_ARCHIVED],
            self::STATUS_QUALIFIED => [self::STATUS_PROPOSAL, self::STATUS_WON, self::STATUS_LOST, self::STATUS_ARCHIVED],
            self::STATUS_PROPOSAL => [self::STATUS_WON, self::STATUS_LOST, self::STATUS_QUALIFIED, self::STATUS_ARCHIVED],
            self::STATUS_WON => [self::STATUS_ARCHIVED],
            self::STATUS_LOST => [self::STATUS_ARCHIVED, self::STATUS_NEW],
            self::STATUS_ARCHIVED => [],
        ];
    }

    /**
     * @return array{ok: bool, lead_id: int, from: string, to: string}
     */
    public function transition(int $leadId, string $toStatus, ?string $reason = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $lead = CrmSupport::assertLead($leadId, $companyId);
        $from = (string) ($lead['workflow_status'] ?? self::STATUS_NEW);
        $to = trim($toStatus);
        if (!in_array($to, self::statuses(), true)) {
            throw new \InvalidArgumentException('invalid_workflow_status');
        }
        $allowed = self::allowedTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
        ], CrmSupport::actorFields(false));
        if ($to === self::STATUS_ARCHIVED) {
            $update['status'] = 'archived';
        }

        (new CrmLead())->update($leadId, $update);

        (new CrmStatusHistory())->create([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => CrmSupport::userId(),
        ]);

        (new CrmTimelineService())->record(
            'workflow',
            'Lead status: ' . $from . ' → ' . $to,
            $reason,
            'lead',
            $leadId,
            ['lead_id' => $leadId]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.workflow', 'crm_lead', $leadId, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
        }

        return ['ok' => true, 'lead_id' => $leadId, 'from' => $from, 'to' => $to];
    }
}
