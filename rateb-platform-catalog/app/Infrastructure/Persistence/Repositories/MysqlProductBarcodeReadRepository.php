<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeReadRepositoryInterface;

final class MysqlProductBarcodeReadRepository extends BaseRepository implements ProductBarcodeReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_barcodes';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT uuid, barcode, barcode_type, is_primary FROM product_barcodes
             WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listByProductUuid(string $productUuid): array
    {
        return $this->fetchAll(
            'SELECT pb.uuid, pb.barcode, pb.barcode_type, pb.is_primary
             FROM product_barcodes pb
             INNER JOIN products p ON p.id = pb.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :product_uuid AND pb.deleted_at IS NULL
             ORDER BY pb.is_primary DESC, pb.id ASC',
            ['product_uuid' => $productUuid]
        );
    }
}
