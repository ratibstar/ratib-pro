<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SearchIndexReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listProductsForIndex(string $locale, int $afterId, int $limit): array;

    /**
     * @return array<string, mixed>|null
     */
    public function buildProductDocument(string $productUuid, string $locale): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listVariantsForProduct(string $productUuid, string $locale): array;

    /**
     * @return array<string, mixed>|null
     */
    public function buildVariantDocument(string $variantUuid, string $locale): ?array;
}
