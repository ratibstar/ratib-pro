<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EamTimelineEvent;

/** Append-only EAM timeline events. */
final class AssetTimelineService
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public function record(
        string $eventType,
        string $title,
        ?string $body,
        ?int $assetId,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?array $meta = null
    ): int {
        $companyId = AssetSupport::requireCompanyId();
        $metaJson = null;
        if ($meta !== null) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $metaJson = $encoded !== false ? $encoded : null;
        }

        return (int) (new EamTimelineEvent())->create([
            'public_uuid' => AssetSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => AssetSupport::branchId(),
            'asset_id' => $assetId,
            'event_type' => substr($eventType, 0, 60),
            'title' => substr($title, 0, 190),
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'meta_json' => $metaJson,
            'created_by' => AssetSupport::userId(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 20): array
    {
        $companyId = AssetSupport::requireCompanyId();
        $safe = max(1, min(100, $limit));
        $rows = (new EamTimelineEvent())->query(
            'SELECT * FROM rateb_eam_timeline WHERE company_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listForAsset(int $assetId, int $limit = 50): array
    {
        $companyId = AssetSupport::requireCompanyId();
        AssetSupport::assertAsset($assetId, $companyId);
        $safe = max(1, min(200, $limit));
        $rows = (new EamTimelineEvent())->query(
            'SELECT * FROM rateb_eam_timeline WHERE company_id = :cid AND asset_id = :aid
             ORDER BY created_at DESC, id DESC LIMIT ' . $safe,
            ['cid' => $companyId, 'aid' => $assetId]
        );

        return is_array($rows) ? $rows : [];
    }
}
