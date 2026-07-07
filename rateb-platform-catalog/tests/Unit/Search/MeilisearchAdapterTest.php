<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery;

catalog_test('InMemorySearchAdapter indexes and searches products by locale', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct([
        'uuid' => 'p1',
        'sku' => 'SKU-1',
        'name' => 'Test Product',
        'boost_score' => 1,
        'category_id' => 'c1',
    ], 'en');

    $result = $adapter->search(new SearchQuery('test', 'en', 'product', [], 'relevance', 10, 0));
    catalog_assert_same(1, $result->total);
    catalog_assert_same('p1', $result->hits[0]['uuid']);
});

catalog_test('InMemorySearchAdapter resolves variant barcode', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexVariant([
        'variant_uuid' => 'v1',
        'product_uuid' => 'p1',
        'sku' => 'VAR-1',
        'barcodes' => ['1234567890'],
    ], 'en');

    $resolved = $adapter->resolveBarcode('1234567890', 'en');
    catalog_assert_true($resolved !== null);
    catalog_assert_same('variant', $resolved->matchType);
});

catalog_test('InMemorySearchAdapter applies facet filters', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct(['uuid' => 'p1', 'name' => 'A', 'category_id' => 'c1', 'boost_score' => 1], 'en');
    $adapter->indexProduct(['uuid' => 'p2', 'name' => 'B', 'category_id' => 'c2', 'boost_score' => 1], 'en');

    $result = $adapter->search(new SearchQuery('', 'en', 'product', ['category_id' => ['c2']], 'relevance', 10, 0));
    catalog_assert_same(1, $result->total);
    catalog_assert_same('p2', $result->hits[0]['uuid']);
});

catalog_test('InMemorySearchAdapter normalizes Arabic query', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct(['uuid' => 'p-ar', 'name' => 'منتج', 'boost_score' => 1], 'ar');

    $result = $adapter->search(new SearchQuery('مَنْتَج', 'ar', 'product', [], 'relevance', 10, 0));
    catalog_assert_same(1, $result->total);
});
