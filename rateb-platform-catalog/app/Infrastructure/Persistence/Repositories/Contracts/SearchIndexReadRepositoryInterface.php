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

    /**
     * @param array<string, list<string>> $facets
     * @return array{hits: list<array<string, mixed>>, total: int, facets: array<string, array<string, int>>}
     */
    public function searchProducts(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array;

    /**
     * @param array<string, list<string>> $facets
     * @return array{hits: list<array<string, mixed>>, total: int, facets: array<string, array<string, int>>}
     */
    public function searchVariants(
        string $normalizedQuery,
        string $locale,
        array $facets,
        string $sort,
        int $limit,
        int $offset
    ): array;

    /**
     * @return array{match_type: string, document: array<string, mixed>}|null
     */
    public function resolveBarcodeDocument(string $barcode, string $locale): ?array;

    public function countPublishedProducts(string $locale): int;

    public function countPublishedVariants(string $locale): int;
}
