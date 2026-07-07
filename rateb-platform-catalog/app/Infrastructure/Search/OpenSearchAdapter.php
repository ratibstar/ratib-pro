<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class OpenSearchAdapter implements SearchAdapterInterface
{
    public function indexProduct(array $document, string $locale): void
    {
        unset($document, $locale);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function deleteProduct(string $productUuid, string $locale): void
    {
        unset($productUuid, $locale);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function indexVariant(array $document, string $locale): void
    {
        unset($document, $locale);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function deleteVariant(string $variantUuid, string $locale): void
    {
        unset($variantUuid, $locale);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult
    {
        unset($barcode, $locale);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function search(SearchQuery $query): SearchResult
    {
        unset($query);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport
    {
        unset($locale, $progress);
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }

    public function healthCheck(): bool
    {
        throw new \LogicException('OpenSearchAdapter is not implemented in Phase 2.7');
    }
}
