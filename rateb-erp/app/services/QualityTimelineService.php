<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\QmsTimeline;

/** Append-only QMS timeline (audit trail for UI). */
final class QualityTimelineService
{
    public function record(
        string $eventType,
        string $title,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $body = null
    ): void {
        $companyId = QualitySupport::requireCompanyId();
        (new QmsTimeline())->create(array_merge([
            'public_uuid' => QualitySupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => QualitySupport::branchId(),
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($title, 0, 190),
            'body' => QualitySupport::nullIfEmpty($body),
            'entity_type' => $entityType !== null ? substr($entityType, 0, 40) : null,
            'entity_id' => $entityId,
            'status' => 'active',
            'version' => 1,
        ], QualitySupport::actorFields(true)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 25): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $items = (new QmsTimeline())->query(
            'SELECT * FROM rateb_qms_timeline WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit,
            ['cid' => $companyId]
        );

        return is_array($items) ? $items : [];
    }

    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function listPaged(int $limit = 25, int $offset = 0): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new QmsTimeline())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_qms_timeline WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new QmsTimeline())->query(
            'SELECT * FROM rateb_qms_timeline WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = QualitySupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $items = (new QmsTimeline())->query(
            'SELECT * FROM rateb_qms_timeline WHERE company_id = :cid AND entity_type = :et'
            . ' AND entity_id = :eid AND deleted_at IS NULL'
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($items) ? $items : [];
    }
}
