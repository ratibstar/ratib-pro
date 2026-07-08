<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductPriceReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForProduct(string $productUuid): array;
}
