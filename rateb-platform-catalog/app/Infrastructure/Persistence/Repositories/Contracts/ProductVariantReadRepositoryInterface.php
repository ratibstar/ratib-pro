<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

interface ProductVariantReadRepositoryInterface extends ReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listByProductUuid(string $productUuid, LocaleContext $locale): array;

    /**
     * @param list<int> $variantIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listBarcodesGroupedByVariantId(array $variantIds): array;

    /**
     * @param list<int> $variantIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listOptionValuesGroupedByVariantId(array $variantIds, LocaleContext $locale): array;
}
