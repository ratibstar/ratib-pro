<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface;

final class MysqlProductRelationReadRepository extends BaseRepository implements ProductRelationReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_relations';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT uuid, relation_type FROM product_relations WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
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
        $nameSelect = $this->translationCoalesce('pt', 'name');

        return $this->fetchAll(
            "SELECT pr.uuid, rp.uuid AS related_product_uuid, pr.relation_type,
                    pr.sort_order, pr.is_bidirectional,
                    rp.sku AS related_product_sku, {$nameSelect} AS related_product_name
             FROM product_relations pr
             INNER JOIN products sp ON sp.id = pr.product_id AND sp.deleted_at IS NULL
             INNER JOIN products rp ON rp.id = pr.related_product_id AND rp.deleted_at IS NULL
             LEFT JOIN product_translations pt_loc ON pt_loc.product_id = rp.id
                AND pt_loc.language_code = :locale AND pt_loc.deleted_at IS NULL
             LEFT JOIN product_translations pt_fb ON pt_fb.product_id = rp.id
                AND pt_fb.language_code = :fallback AND pt_fb.deleted_at IS NULL
             WHERE sp.uuid = :product_uuid AND pr.deleted_at IS NULL
             ORDER BY pr.sort_order ASC, pr.id ASC",
            array_merge(['product_uuid' => $productUuid], $this->localeParams($locale))
        );
    }
}
