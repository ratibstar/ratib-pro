<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SkuUniquenessReadRepositoryInterface
{
    public function existsOnProduct(string $sku): bool;

    public function existsOnVariant(string $sku, ?string $excludeVariantUuid = null): bool;
}
