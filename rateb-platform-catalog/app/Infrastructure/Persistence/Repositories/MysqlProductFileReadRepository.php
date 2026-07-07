<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileReadRepositoryInterface;

final class MysqlProductFileReadRepository extends BaseRepository implements ProductFileReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_files';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT pf.uuid, pf.storage_key, pf.mime_type, pf.file_size_bytes, pf.checksum_sha256,
                    pf.sort_order, at.code AS asset_type_code
             FROM product_files pf
             INNER JOIN asset_types at ON at.id = pf.asset_type_id
             WHERE pf.uuid = :uuid AND pf.deleted_at IS NULL LIMIT 1',
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
            'SELECT pf.id, pf.uuid, pf.storage_key, pf.mime_type, pf.file_size_bytes,
                    pf.checksum_sha256, pf.sort_order, at.code AS asset_type_code
             FROM product_files pf
             INNER JOIN products p ON p.id = pf.product_id AND p.deleted_at IS NULL
             INNER JOIN asset_types at ON at.id = pf.asset_type_id AND at.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pf.deleted_at IS NULL
             ORDER BY pf.sort_order ASC, pf.id ASC',
            ['product_uuid' => $productUuid]
        );
    }

    public function listTranslationsGrouped(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        $inClause = [];
        $params = [];
        foreach ($fileIds as $index => $id) {
            $key = 'fid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT product_file_id, language_code, title, description
             FROM product_file_translations
             WHERE product_file_id IN (' . implode(',', $inClause) . ') AND deleted_at IS NULL',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_file_id'];
            $grouped[$id][] = [
                'language_code' => $row['language_code'],
                'title' => $row['title'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
