<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmTimelineEvent;

/** Phase 17A — CRM activity timeline (append-only). */
final class CrmTimelineService
{
    /**
     * @param array<string, mixed> $links
     */
    public function record(
        string $eventType,
        string $title,
        ?string $body = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $links = []
    ): int {
        $companyId = CrmSupport::requireCompanyId();
        $id = (new CrmTimelineEvent())->create([
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'event_type' => substr(trim($eventType), 0, 40),
            'title' => substr(trim($title), 0, 190),
            'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'lead_id' => CrmSupport::intOrNull($links['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($links['opportunity_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($links['contact_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($links['crm_company_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($links['customer_id'] ?? null),
            'created_by' => CrmSupport::userId(),
        ]);

        return (int) $id;
    }

    /** @return list<array<string, mixed>> */
    public function listForLead(int $leadId, int $limit = 50): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new CrmTimelineEvent())->query(
            'SELECT * FROM rateb_crm_timeline
             WHERE company_id = :cid AND lead_id = :lid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId, 'lid' => $leadId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForCustomer(int $customerId, int $limit = 50): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new CrmTimelineEvent())->query(
            'SELECT * FROM rateb_crm_timeline
             WHERE company_id = :cid AND customer_id = :cuid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId, 'cuid' => $customerId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 25): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new CrmTimelineEvent())->query(
            'SELECT * FROM rateb_crm_timeline
             WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForOpportunity(int $opportunityId, int $limit = 50): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new CrmTimelineEvent())->query(
            'SELECT * FROM rateb_crm_timeline
             WHERE company_id = :cid AND opportunity_id = :oid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $safe,
            ['cid' => $companyId, 'oid' => $opportunityId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForQuotation(int $quotationId, int $limit = 50): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new CrmTimelineEvent())->query(
            "SELECT * FROM rateb_crm_timeline
             WHERE company_id = :cid AND related_type = 'quotation' AND related_id = :qid
             ORDER BY created_at DESC, id DESC
             LIMIT " . $safe,
            ['cid' => $companyId, 'qid' => $quotationId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Expanded feed: leads, opportunities, quotes, activities, user actions.
     *
     * @return list<array<string, mixed>>
     */
    public function listExpanded(int $limit = 40, ?string $eventType = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid';
        if ($eventType !== null && trim($eventType) !== '') {
            $where .= ' AND event_type = :et';
            $params['et'] = substr(trim($eventType), 0, 40);
        } else {
            $where .= " AND (
                event_type LIKE 'workflow%'
                OR event_type LIKE 'quotation%'
                OR event_type LIKE 'conversion%'
                OR event_type LIKE 'opportunity%'
                OR event_type IN ('activity','call','meeting','task','task_done','note','lead_created','lead_updated')
                OR related_type IN ('lead','opportunity','quotation','activity','call','meeting','task','note','customer')
            )";
        }
        $rows = (new CrmTimelineEvent())->query(
            'SELECT * FROM rateb_crm_timeline WHERE ' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            $params
        );

        return is_array($rows) ? $rows : [];
    }
}
