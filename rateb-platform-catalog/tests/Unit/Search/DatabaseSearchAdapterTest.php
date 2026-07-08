<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Search\DatabaseSearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery;
use Rateb\PlatformCatalog\Tests\Support\StubSearchIndexReadRepository;

catalog_test('DatabaseSearchAdapter delegates product search to repository', static function (): void {
    $repository = new class extends StubSearchIndexReadRepository {
        public function searchProducts(
            string $normalizedQuery,
            string $locale,
            array $facets,
            string $sort,
            int $limit,
            int $offset
        ): array {
            catalog_assert_same('widget', $normalizedQuery);
            catalog_assert_same('en', $locale);

            return [
                'hits' => [['uuid' => 'p1', 'name' => 'Widget']],
                'total' => 1,
                'facets' => ['category_id' => ['c1' => 1]],
            ];
        }
    };

    $adapter = new DatabaseSearchAdapter($repository);
    $result = $adapter->search(new SearchQuery('Widget', 'en', 'product', ['category_id' => ['c1']], 'relevance', 10, 0));

    catalog_assert_same(1, $result->total);
    catalog_assert_same('p1', $result->hits[0]['uuid']);
    catalog_assert_same(['c1' => 1], $result->facets['category_id']);
});

catalog_test('DatabaseSearchAdapter resolves barcode via repository', static function (): void {
    $repository = new class extends StubSearchIndexReadRepository {
        public function resolveBarcodeDocument(string $barcode, string $locale): ?array
        {
            catalog_assert_same('12345', $barcode);
            catalog_assert_same('ar', $locale);

            return [
                'match_type' => 'variant',
                'document' => ['variant_uuid' => 'v1', 'barcodes' => ['12345']],
            ];
        }
    };

    $adapter = new DatabaseSearchAdapter($repository);
    $resolved = $adapter->resolveBarcode('12345', 'ar');

    catalog_assert_true($resolved !== null);
    catalog_assert_same('variant', $resolved->matchType);
    catalog_assert_same('v1', $resolved->document['variant_uuid']);
});

catalog_test('DatabaseSearchAdapter index operations are no-ops', static function (): void {
    $adapter = new DatabaseSearchAdapter(new StubSearchIndexReadRepository());
    $adapter->indexProduct(['uuid' => 'p1'], 'en');
    $adapter->deleteProduct('p1', 'en');
    $adapter->indexVariant(['variant_uuid' => 'v1'], 'en');
    $adapter->deleteVariant('v1', 'en');
    catalog_assert_true(true);
});

catalog_test('DatabaseSearchAdapter reindexLocale returns published counts', static function (): void {
    $repository = new class extends StubSearchIndexReadRepository {
        public function countPublishedProducts(string $locale): int
        {
            catalog_assert_same('en', $locale);

            return 12;
        }

        public function countPublishedVariants(string $locale): int
        {
            return 34;
        }
    };

    $adapter = new DatabaseSearchAdapter($repository);
    $report = $adapter->reindexLocale('en');

    catalog_assert_same('en', $report->locale);
    catalog_assert_same(12, $report->productsIndexed);
    catalog_assert_same(34, $report->variantsIndexed);
});

catalog_test('DatabaseSearchAdapter normalizes Arabic queries before search', static function (): void {
    $captured = '';
    $repository = new class($captured) extends StubSearchIndexReadRepository {
        public function __construct(private string &$captured)
        {
        }

        public function searchProducts(
            string $normalizedQuery,
            string $locale,
            array $facets,
            string $sort,
            int $limit,
            int $offset
        ): array {
            $this->captured = $normalizedQuery;

            return ['hits' => [], 'total' => 0, 'facets' => []];
        }
    };

    $adapter = new DatabaseSearchAdapter($repository);
    $adapter->search(new SearchQuery('مَنْتَج', 'ar', 'product'));

    catalog_assert_same('منتج', $captured);
});

catalog_test('DatabaseSearchAdapter healthCheck returns bool from database ping', static function (): void {
    $adapter = new DatabaseSearchAdapter(new StubSearchIndexReadRepository());
    catalog_assert_true(is_bool($adapter->healthCheck()));
});
