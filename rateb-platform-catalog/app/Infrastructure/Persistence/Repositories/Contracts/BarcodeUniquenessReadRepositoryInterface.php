<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface BarcodeUniquenessReadRepositoryInterface
{
    public function existsOnProductBarcode(string $barcode, ?string $excludeProductBarcodeUuid = null): bool;

    public function existsOnVariantBarcode(string $barcode, ?string $excludeVariantBarcodeUuid = null): bool;
}
