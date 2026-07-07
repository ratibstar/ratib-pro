<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationReadRepositoryInterface;

final class MysqlProductTranslationReadRepository extends BaseRepository implements ProductTranslationReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_translations';
    }

    public function listForProduct(string $productUuid, LocaleContext $locale): array
    {
        $sql = 'SELECT pt.uuid, pt.language_code,
                       pt.name, pt.short_description, pt.description
                FROM product_translations pt
                INNER JOIN products p ON p.id = pt.product_id AND p.deleted_at IS NULL
                WHERE p.uuid = :product_uuid AND ' . $this->notDeletedClause('pt') . '
                ORDER BY pt.language_code ASC';

        return $this->fetchAll($sql, ['product_uuid' => $productUuid]);
    }
}
