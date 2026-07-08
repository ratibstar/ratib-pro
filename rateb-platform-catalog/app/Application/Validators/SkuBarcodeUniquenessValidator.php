<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BarcodeUniquenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SkuUniquenessReadRepositoryInterface;

final class SkuBarcodeUniquenessValidator
{
    public function __construct(
        private readonly SkuUniquenessReadRepositoryInterface $skuUniqueness,
        private readonly BarcodeUniquenessReadRepositoryInterface $barcodeUniqueness
    ) {
    }

    public function assertSkuAvailable(string $sku, ?string $excludeVariantUuid = null): void
    {
        if ($this->skuUniqueness->existsOnProduct($sku)) {
            throw new \InvalidArgumentException('SKU already exists on a product');
        }

        if ($this->skuUniqueness->existsOnVariant($sku, $excludeVariantUuid)) {
            throw new \InvalidArgumentException('SKU already exists on a variant');
        }
    }

    public function assertBarcodeAvailable(string $barcode, ?string $excludeProductBarcodeUuid = null, ?string $excludeVariantBarcodeUuid = null): void
    {
        if ($this->barcodeUniqueness->existsOnProductBarcode($barcode, $excludeProductBarcodeUuid)) {
            throw new \InvalidArgumentException('Barcode already exists');
        }

        if ($this->barcodeUniqueness->existsOnVariantBarcode($barcode, $excludeVariantBarcodeUuid)) {
            throw new \InvalidArgumentException('Barcode already exists');
        }
    }
}
