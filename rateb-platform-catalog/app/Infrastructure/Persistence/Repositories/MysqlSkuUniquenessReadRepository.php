<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SkuUniquenessReadRepositoryInterface;

final class MysqlSkuUniquenessReadRepository extends BaseRepository implements SkuUniquenessReadRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function existsOnProduct(string $sku): bool
    {
        return $this->fetchOne(
            'SELECT id FROM products WHERE sku = :sku AND deleted_at IS NULL LIMIT 1',
            ['sku' => $sku]
        ) !== null;
    }

    public function existsOnVariant(string $sku, ?string $excludeVariantUuid = null): bool
    {
        $sql = 'SELECT id FROM product_variants WHERE sku = :sku AND deleted_at IS NULL';
        $params = ['sku' => $sku];
        if ($excludeVariantUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeVariantUuid;
        }
        $sql .= ' LIMIT 1';

        return $this->fetchOne($sql, $params) !== null;
    }
}
