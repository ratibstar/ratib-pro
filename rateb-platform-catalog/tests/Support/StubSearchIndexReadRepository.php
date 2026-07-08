<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Tests\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;

class StubSearchIndexReadRepository implements SearchIndexReadRepositoryInterface
{
    public function listProductsForIndex(string $locale, int $afterId, int $limit): array
    {
        return [];
    }

    public function buildProductDocument(string $productUuid, string $locale): ?array
    {
        return null;
    }

    public function listVariantsForProduct(string $productUuid, string $locale): array
    {
        return [];
    }

    public function buildVariantDocument(string $variantUuid, string $locale): ?array
    {
        return null;
    }

    public function searchProducts(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array {
        return ['hits' => [], 'total' => 0, 'facets' => []];
    }

    public function searchVariants(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array {
        return ['hits' => [], 'total' => 0, 'facets' => []];
    }

    public function resolveBarcodeDocument(string $barcode, string $locale): ?array
    {
        return null;
    }

    public function countPublishedProducts(string $locale): int
    {
        return 0;
    }

    public function countPublishedVariants(string $locale): int
    {
        return 0;
    }
}
