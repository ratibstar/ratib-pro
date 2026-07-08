<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceReadRepositoryInterface;

final class MysqlProductPriceReadRepository extends BaseRepository implements ProductPriceReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_prices';
    }

    public function listForProduct(string $productUuid): array
    {
        return $this->fetchAll(
            'SELECT pp.uuid, pp.currency_code, pp.cost, pp.msrp, pp.default_price,
                    pp.effective_from, pp.effective_to, pp.is_active,
                    pp.created_at, pp.updated_at
             FROM product_prices pp
             INNER JOIN products p ON p.id = pp.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pp.deleted_at IS NULL
             ORDER BY pp.currency_code ASC',
            ['product_uuid' => $productUuid]
        );
    }
}
