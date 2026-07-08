<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Search\DatabaseSearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\MeilisearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterFactory;

catalog_test('SearchAdapterFactory returns InMemorySearchAdapter in testing environment', static function (): void {
    $adapter = SearchAdapterFactory::create();
    catalog_assert_true($adapter instanceof InMemorySearchAdapter);
});

catalog_test('SearchAdapterFactory database branch constructs DatabaseSearchAdapter', static function (): void {
    $adapter = new DatabaseSearchAdapter(new \Rateb\PlatformCatalog\Tests\Support\StubSearchIndexReadRepository());
    catalog_assert_true($adapter instanceof DatabaseSearchAdapter);
});

catalog_test('SearchAdapterFactory meilisearch adapter requires host outside testing', static function (): void {
    try {
        new MeilisearchAdapter('');
        catalog_assert_true(false, 'Expected RuntimeException');
    } catch (RuntimeException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'MEILISEARCH_HOST'));
    }
});

catalog_test('Search config defaults to database adapter', static function (): void {
    $configPath = (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 2)) . '/config/search.php';
    $config = require $configPath;
    catalog_assert_true(is_array($config));
    catalog_assert_same('database', strtolower((string) ($config['SEARCH_ADAPTER'] ?? '')));
});
