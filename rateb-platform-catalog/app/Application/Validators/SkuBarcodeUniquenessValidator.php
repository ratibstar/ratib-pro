<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\BaseRepository;

final class SkuBarcodeUniquenessValidator extends BaseRepository
{
    protected function table(): string
    {
        return 'products';
    }

    public function assertSkuAvailable(string $sku, ?string $excludeVariantUuid = null): void
    {
        $product = $this->fetchOne(
            'SELECT id FROM products WHERE sku = :sku AND deleted_at IS NULL LIMIT 1',
            ['sku' => $sku]
        );
        if ($product !== null) {
            throw new \InvalidArgumentException('SKU already exists on a product');
        }

        $sql = 'SELECT id FROM product_variants WHERE sku = :sku AND deleted_at IS NULL';
        $params = ['sku' => $sku];
        if ($excludeVariantUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeVariantUuid;
        }
        $sql .= ' LIMIT 1';

        $variant = $this->fetchOne($sql, $params);
        if ($variant !== null) {
            throw new \InvalidArgumentException('SKU already exists on a variant');
        }
    }

    public function assertBarcodeAvailable(string $barcode, ?string $excludeProductBarcodeUuid = null, ?string $excludeVariantBarcodeUuid = null): void
    {
        $productBarcode = $this->fetchOne(
            'SELECT id FROM product_barcodes WHERE barcode = :barcode AND deleted_at IS NULL
             AND (:exclude_pb IS NULL OR uuid <> :exclude_pb) LIMIT 1',
            ['barcode' => $barcode, 'exclude_pb' => $excludeProductBarcodeUuid]
        );
        if ($productBarcode !== null) {
            throw new \InvalidArgumentException('Barcode already exists');
        }

        $variantBarcode = $this->fetchOne(
            'SELECT id FROM product_variant_barcodes WHERE barcode = :barcode AND deleted_at IS NULL
             AND (:exclude_vb IS NULL OR uuid <> :exclude_vb) LIMIT 1',
            ['barcode' => $barcode, 'exclude_vb' => $excludeVariantBarcodeUuid]
        );
        if ($variantBarcode !== null) {
            throw new \InvalidArgumentException('Barcode already exists');
        }
    }
}
