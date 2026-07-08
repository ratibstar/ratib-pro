<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductPriceWriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $prices
     */
    public function replaceForProduct(string $productUuid, array $prices): void;
}
