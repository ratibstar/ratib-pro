<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MfgTimelineEvent;

/** Append-only Manufacturing timeline events. */
final class ManufacturingTimelineService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $eventType,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null
    ): int {
        $companyId = ManufacturingSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new MfgTimelineEvent())->create(array_merge([
            'public_uuid' => ManufacturingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ManufacturingSupport::branchId(),
            'entity_type' => $entityType !== null ? substr($entityType, 0, 40) : null,
            'entity_id' => $entityId,
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($message, 0, 190),
            'body' => $message,
            'meta_json' => $metaJson,
            'version' => 1,
        ], ManufacturingSupport::actorFields(true)));
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new MfgTimelineEvent())->query(
            'SELECT * FROM rateb_mfg_timeline WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = ManufacturingSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new MfgTimelineEvent())->query(
            'SELECT * FROM rateb_mfg_timeline
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid AND deleted_at IS NULL
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }
}
