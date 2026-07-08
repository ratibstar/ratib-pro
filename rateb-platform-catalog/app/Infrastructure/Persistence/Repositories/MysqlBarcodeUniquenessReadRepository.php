<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BarcodeUniquenessReadRepositoryInterface;

final class MysqlBarcodeUniquenessReadRepository extends BaseRepository implements BarcodeUniquenessReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_barcodes';
    }

    public function existsOnProductBarcode(string $barcode, ?string $excludeProductBarcodeUuid = null): bool
    {
        return $this->fetchOne(
            'SELECT id FROM product_barcodes WHERE barcode = :barcode AND deleted_at IS NULL
             AND (:exclude_pb IS NULL OR uuid <> :exclude_pb) LIMIT 1',
            ['barcode' => $barcode, 'exclude_pb' => $excludeProductBarcodeUuid]
        ) !== null;
    }

    public function existsOnVariantBarcode(string $barcode, ?string $excludeVariantBarcodeUuid = null): bool
    {
        return $this->fetchOne(
            'SELECT id FROM product_variant_barcodes WHERE barcode = :barcode AND deleted_at IS NULL
             AND (:exclude_vb IS NULL OR uuid <> :exclude_vb) LIMIT 1',
            ['barcode' => $barcode, 'exclude_vb' => $excludeVariantBarcodeUuid]
        ) !== null;
    }
}
