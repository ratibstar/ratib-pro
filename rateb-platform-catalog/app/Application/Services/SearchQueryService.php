<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\SearchPolicy;
use Rateb\PlatformCatalog\Application\Support\LocaleMetaBuilder;
use Rateb\PlatformCatalog\Infrastructure\Search\BarcodeResolveResult;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterInterface;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery;

final class SearchQueryService
{
    public function __construct(
        private readonly SearchAdapterInterface $searchAdapter,
        private readonly SearchPolicy $policy,
        private readonly LocaleResolverService $localeResolver
    ) {
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function searchProducts(array $queryParams, ?LocaleContext $locale = null): array
    {
        $this->policy->search();
        $this->assertNotInMaintenance();
        $locale ??= $this->localeResolver->resolveFromRequest();

        $result = $this->searchAdapter->search($this->buildQuery($queryParams, $locale, 'product'));

        return [
            'items' => $result->hits,
            'meta' => array_merge(LocaleMetaBuilder::build($locale, $result->hits), [
                'total' => $result->total,
                'facets' => $result->facets,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function searchVariants(array $queryParams, ?LocaleContext $locale = null): array
    {
        $this->policy->search();
        $this->assertNotInMaintenance();
        $locale ??= $this->localeResolver->resolveFromRequest();

        $result = $this->searchAdapter->search($this->buildQuery($queryParams, $locale, 'variant'));

        return [
            'items' => $result->hits,
            'meta' => array_merge(LocaleMetaBuilder::build($locale, $result->hits), [
                'total' => $result->total,
                'facets' => $result->facets,
            ]),
        ];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function resolveBarcode(string $barcode, ?LocaleContext $locale = null): array
    {
        $this->policy->search();
        $locale ??= $this->localeResolver->resolveFromRequest();
        $resolved = $this->searchAdapter->resolveBarcode($barcode, $locale->locale);

        return [
            'item' => $resolved?->toArray(),
            'meta' => LocaleMetaBuilder::build($locale, $resolved !== null ? [$resolved->document] : []),
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function buildQuery(array $queryParams, LocaleContext $locale, string $indexType): SearchQuery
    {
        $facets = [];
        foreach ($queryParams as $key => $value) {
            if (!str_starts_with((string) $key, 'facet[') || !str_ends_with((string) $key, ']')) {
                continue;
            }
            $facetKey = substr((string) $key, 6, -1);
            $facets[$facetKey] = is_array($value) ? array_map('strval', $value) : [(string) $value];
        }

        return new SearchQuery(
            query: (string) ($queryParams['q'] ?? ''),
            locale: $locale->locale,
            indexType: $indexType,
            facets: $facets,
            sort: (string) ($queryParams['sort'] ?? 'relevance'),
            limit: isset($queryParams['limit']) ? max(1, min(200, (int) $queryParams['limit'])) : 50,
            offset: isset($queryParams['offset']) ? max(0, (int) $queryParams['offset']) : 0
        );
    }

    private function assertNotInMaintenance(): void
    {
        $config = is_file((defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : '') . '/config/search.php')
            ? require (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : '') . '/config/search.php'
            : [];
        if (is_array($config) && !empty($config['SEARCH_MAINTENANCE_MODE'])) {
            throw new \RuntimeException('Search maintenance in progress', 503);
        }
    }
}
