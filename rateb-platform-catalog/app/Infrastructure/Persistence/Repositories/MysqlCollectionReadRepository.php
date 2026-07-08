<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionReadRepositoryInterface;

final class MysqlCollectionReadRepository extends BaseRepository implements CollectionReadRepositoryInterface
{
    protected function table(): string
    {
        return 'collections';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT c.uuid, c.slug, c.collection_type, c.image_path, c.sort_order, c.status,
                       c.publish_at, c.archive_at, c.created_at, c.updated_at,
                       ' . $this->translationSelect('ct', 'name') . ',
                       ' . $this->translationSelect('ct', 'description') . ',
                       COALESCE(ct_loc.language_code, ct_fb.language_code) AS resolved_language_code
                FROM collections c
                ' . $this->translationJoin('c', 'id', 'collection_translations', 'ct', 'collection_id') . '
                WHERE c.uuid = :uuid AND ' . $this->notDeletedClause('c') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT c.uuid, c.slug, c.collection_type, c.image_path, c.sort_order, c.status,
                       c.publish_at, c.archive_at, c.created_at, c.updated_at,
                       ' . $this->translationSelect('ct', 'name') . ',
                       ' . $this->translationSelect('ct', 'description') . ',
                       COALESCE(ct_loc.language_code, ct_fb.language_code) AS resolved_language_code
                FROM collections c
                ' . $this->translationJoin('c', 'id', 'collection_translations', 'ct', 'collection_id') . '
                WHERE ' . $this->notDeletedClause('c') . '
                ORDER BY c.sort_order ASC, c.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function listProducts(string $collectionUuid, LocaleContext $locale, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $nameSelect = $this->translationCoalesce('pt', 'name');

        return $this->fetchAll(
            'SELECT cp.uuid, p.uuid AS product_uuid, p.sku, cp.sort_order,
                    ' . $nameSelect . ' AS product_name,
                    COALESCE(pt_loc.language_code, pt_fb.language_code) AS resolved_language_code
             FROM collection_products cp
             INNER JOIN collections c ON c.id = cp.collection_id AND c.deleted_at IS NULL
             INNER JOIN products p ON p.id = cp.product_id AND p.deleted_at IS NULL
             LEFT JOIN product_translations pt_loc ON pt_loc.product_id = p.id
                AND pt_loc.language_code = :locale AND pt_loc.deleted_at IS NULL
             LEFT JOIN product_translations pt_fb ON pt_fb.product_id = p.id
                AND pt_fb.language_code = :fallback AND pt_fb.deleted_at IS NULL
             WHERE c.uuid = :collection_uuid AND cp.deleted_at IS NULL
             ORDER BY cp.sort_order ASC, cp.id ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            array_merge(['collection_uuid' => $collectionUuid], $this->localeParams($locale))
        );
    }
}
