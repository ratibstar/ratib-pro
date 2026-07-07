<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

interface SearchAdapterInterface
{
    /**
     * @param array<string, mixed> $document
     */
    public function indexProduct(array $document, string $locale): void;

    public function deleteProduct(string $productUuid, string $locale): void;

    /**
     * @param array<string, mixed> $document
     */
    public function indexVariant(array $document, string $locale): void;

    public function deleteVariant(string $variantUuid, string $locale): void;

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult;

    public function search(SearchQuery $query): SearchResult;

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport;

    public function healthCheck(): bool;
}
