<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;

final class MysqlProductImageReadRepository extends BaseRepository implements ProductImageReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_images';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        return $this->findByUuidAndVariant($uuid, 'original');
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listByProductUuid(string $productUuid, LocaleContext $locale): array
    {
        return $this->fetchAll(
            'SELECT pi.id, pi.uuid, pi.storage_key, pi.mime_type, pi.width, pi.height,
                    pi.file_size_bytes, pi.variant, pi.sort_order, pi.is_primary,
                    pi.optimized, pi.compressed, pi.checksum_sha256,
                    at.code AS asset_type_code
             FROM product_images pi
             INNER JOIN products p ON p.id = pi.product_id AND p.deleted_at IS NULL
             INNER JOIN asset_types at ON at.id = pi.asset_type_id AND at.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pi.deleted_at IS NULL
             ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC',
            ['product_uuid' => $productUuid]
        );
    }

    public function findByUuidAndVariant(string $imageUuid, string $variant): ?array
    {
        return $this->fetchOne(
            'SELECT pi.uuid, pi.storage_key, pi.mime_type, pi.file_size_bytes, pi.variant,
                    at.code AS asset_type_code
             FROM product_images pi
             INNER JOIN asset_types at ON at.id = pi.asset_type_id
             WHERE pi.uuid = :uuid AND pi.variant = :variant AND pi.deleted_at IS NULL LIMIT 1',
            ['uuid' => $imageUuid, 'variant' => $variant]
        );
    }

    public function listTranslationsGrouped(array $imageIds): array
    {
        if ($imageIds === []) {
            return [];
        }

        $inClause = [];
        $params = [];
        foreach ($imageIds as $index => $id) {
            $key = 'iid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT product_image_id, language_code, alt_text
             FROM product_image_translations
             WHERE product_image_id IN (' . implode(',', $inClause) . ') AND deleted_at IS NULL',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_image_id'];
            $grouped[$id][] = [
                'language_code' => $row['language_code'],
                'alt_text' => $row['alt_text'],
            ];
        }

        return $grouped;
    }
}
