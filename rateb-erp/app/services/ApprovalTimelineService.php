<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EapTimelineEvent;

/** Append-only EAP timeline events. */
final class ApprovalTimelineService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $eventType,
        string $title,
        ?string $body,
        ?int $requestId,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?array $meta = null
    ): int {
        $companyId = ApprovalSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new EapTimelineEvent())->create([
            'public_uuid' => ApprovalSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ApprovalSupport::branchId(),
            'request_id' => $requestId,
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($title, 0, 190),
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'meta_json' => $metaJson,
            'created_by' => ApprovalSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new EapTimelineEvent())->query(
            'SELECT * FROM rateb_eap_timeline WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForRequest(int $requestId, int $limit = 50): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        ApprovalSupport::assertRequest($requestId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new EapTimelineEvent())->query(
            'SELECT * FROM rateb_eap_timeline WHERE company_id = :cid AND request_id = :rid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'rid' => $requestId]
        );

        return is_array($rows) ? $rows : [];
    }
}
