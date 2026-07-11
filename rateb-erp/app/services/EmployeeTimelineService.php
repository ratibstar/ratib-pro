<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\HrmTimeline;

/** Append-only Enterprise HR timeline events (rateb_hrm_timeline). */
final class EmployeeTimelineService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function append(
        string $entityType,
        int $entityId,
        string $eventType,
        string $title,
        ?string $body = null,
        ?array $meta = null
    ): int {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }
        $bodyText = $body !== null && trim($body) !== '' ? trim($body) : null;

        return (int) (new HrmTimeline())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::branchId(),
            'entity_type' => substr($entityType, 0, 40),
            'entity_id' => $entityId,
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($title, 0, 190),
            'body' => $bodyText,
            'meta_json' => $metaJson,
            'status' => 'active',
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new HrmTimeline())->query(
            'SELECT * FROM rateb_hrm_timeline WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = HumanResourcesSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new HrmTimeline())->query(
            'SELECT * FROM rateb_hrm_timeline
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }
}
