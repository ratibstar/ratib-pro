<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EprocTimelineEvent;

/** Append-only EPROC timeline events. */
final class ProcurementTimelineService
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
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new EprocTimelineEvent())->create([
            'public_uuid' => ProcurementEnterpriseSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => ProcurementEnterpriseSupport::branchId(),
            'entity_type' => $entityType !== null ? substr($entityType, 0, 40) : null,
            'entity_id' => $entityId,
            'event_type' => substr($eventType, 0, 60),
            'message' => substr($message, 0, 255),
            'meta_json' => $metaJson,
            'created_by' => ProcurementEnterpriseSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new EprocTimelineEvent())->query(
            'SELECT * FROM rateb_eproc_timeline WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $companyId = ProcurementEnterpriseSupport::requireCompanyId();
        $safe = max(1, min(200, $limit));
        $rows = (new EprocTimelineEvent())->query(
            'SELECT * FROM rateb_eproc_timeline
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]
        );

        return is_array($rows) ? $rows : [];
    }
}
