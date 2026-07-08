<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

use Rateb\PlatformCatalog\Application\Support\ArabicNormalizer;
use Rateb\PlatformCatalog\Core\Database;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;

final class DatabaseSearchAdapter implements SearchAdapterInterface
{
    public function __construct(
        private readonly SearchIndexReadRepositoryInterface $searchIndexRepository
    ) {
    }

    public function indexProduct(array $document, string $locale): void
    {
        unset($document, $locale);
    }

    public function deleteProduct(string $productUuid, string $locale): void
    {
        unset($productUuid, $locale);
    }

    public function indexVariant(array $document, string $locale): void
    {
        unset($document, $locale);
    }

    public function deleteVariant(string $variantUuid, string $locale): void
    {
        unset($variantUuid, $locale);
    }

    public function resolveBarcode(string $barcode, string $locale): ?BarcodeResolveResult
    {
        $resolved = $this->searchIndexRepository->resolveBarcodeDocument($barcode, $locale);
        if ($resolved === null) {
            return null;
        }

        return new BarcodeResolveResult(
            (string) $resolved['match_type'],
            $resolved['document']
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $needle = $this->normalizeQuery($query->query, $query->locale);

        $payload = $query->indexType === 'variant'
            ? $this->searchIndexRepository->searchVariants(
                $needle,
                $query->locale,
                $query->facets,
                $query->sort,
                $query->limit,
                $query->offset
            )
            : $this->searchIndexRepository->searchProducts(
                $needle,
                $query->locale,
                $query->facets,
                $query->sort,
                $query->limit,
                $query->offset
            );

        return new SearchResult($payload['hits'], $payload['total'], $payload['facets']);
    }

    public function reindexLocale(string $locale, ?callable $progress = null): ReindexReport
    {
        if ($progress !== null) {
            $progress(['locale' => $locale, 'status' => 'database_adapter_noop']);
        }

        return new ReindexReport(
            $locale,
            $this->searchIndexRepository->countPublishedProducts($locale),
            $this->searchIndexRepository->countPublishedVariants($locale)
        );
    }

    public function healthCheck(): bool
    {
        return Database::ping(true);
    }

    private function normalizeQuery(string $query, string $locale): string
    {
        $query = function_exists('mb_strtolower') ? mb_strtolower(trim($query)) : strtolower(trim($query));
        if ($locale === 'ar') {
            return ArabicNormalizer::normalizeForSearch($query);
        }

        return $query;
    }
}
