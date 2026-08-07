<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmEntityStatusHistory;
use Rateb\App\Models\CrmQuotation;

/**
 * Phase 2 — Quotation lifecycle authority.
 * Draft → Sent → Accepted | Rejected | Expired. No invoice conversion here.
 */
final class CrmQuotationWorkflowService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_REJECTED],
            self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED],
            self::STATUS_ACCEPTED => [],
            self::STATUS_REJECTED => [self::STATUS_DRAFT],
            self::STATUS_EXPIRED => [self::STATUS_DRAFT],
        ];
    }

    /**
     * @return array{ok: bool, quotation_id: int, from: string, to: string}
     */
    public function transition(int $quotationId, string $toStatus, ?string $reason = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $quote = (new CrmQuotationService())->find($quotationId);
        if ($quote === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        $from = (string) ($quote['status'] ?? self::STATUS_DRAFT);
        $to = trim($toStatus);
        if (!in_array($to, self::statuses(), true)) {
            throw new \InvalidArgumentException('invalid_quotation_status');
        }
        $allowed = self::allowedTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('quotation_transition_denied');
        }

        (new CrmQuotation())->update($quotationId, array_merge([
            'status' => $to,
        ], CrmSupport::actorFields(false)));

        (new CrmEntityStatusHistory())->create([
            'company_id' => $companyId,
            'entity_type' => 'quotation',
            'entity_id' => $quotationId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? substr(trim($reason), 0, 255) : null,
            'created_by' => CrmSupport::userId(),
        ]);

        (new CrmTimelineService())->record(
            'quotation_' . $to,
            'Quotation status: ' . $from . ' → ' . $to,
            $reason,
            'quotation',
            $quotationId,
            [
                'lead_id' => CrmSupport::intOrNull($quote['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($quote['opportunity_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($quote['customer_id'] ?? null),
            ]
        );

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.quotation.workflow', 'crm_quotation', $quotationId, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
        }

        return ['ok' => true, 'quotation_id' => $quotationId, 'from' => $from, 'to' => $to];
    }
}
