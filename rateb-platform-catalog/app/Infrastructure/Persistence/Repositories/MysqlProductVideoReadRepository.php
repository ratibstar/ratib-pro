<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;

final class MysqlProductVideoReadRepository extends BaseRepository implements ProductVideoReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_videos';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT pv.uuid, pv.video_type, pv.external_id, pv.external_url,
                    pv.storage_key, pv.thumbnail_storage_key, pv.duration_seconds, pv.sort_order,
                    at.code AS asset_type_code
             FROM product_videos pv
             INNER JOIN asset_types at ON at.id = pv.asset_type_id
             WHERE pv.uuid = :uuid AND pv.deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listByProductUuid(string $productUuid, LocaleContext $locale): array
    {
        unset($locale);

        return $this->fetchAll(
            'SELECT pv.id, pv.uuid, pv.video_type, pv.external_id, pv.external_url,
                    pv.storage_key, pv.thumbnail_storage_key, pv.duration_seconds, pv.sort_order,
                    at.code AS asset_type_code
             FROM product_videos pv
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             INNER JOIN asset_types at ON at.id = pv.asset_type_id AND at.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pv.deleted_at IS NULL
             ORDER BY pv.sort_order ASC, pv.id ASC',
            ['product_uuid' => $productUuid]
        );
    }

    public function listTranslationsGrouped(array $videoIds): array
    {
        if ($videoIds === []) {
            return [];
        }

        $inClause = [];
        $params = [];
        foreach ($videoIds as $index => $id) {
            $key = 'vid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT product_video_id, language_code, title, description
             FROM product_video_translations
             WHERE product_video_id IN (' . implode(',', $inClause) . ') AND deleted_at IS NULL',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_video_id'];
            $grouped[$id][] = [
                'language_code' => $row['language_code'],
                'title' => $row['title'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
