<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\CursorPagination;
use Rateb\PlatformCatalog\Application\Support\SecretCipher;
use Rateb\PlatformCatalog\Application\Support\WebhookHmacSigner;
use Rateb\PlatformCatalog\Infrastructure\Cache\FileCacheAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;
use Rateb\PlatformCatalog\Infrastructure\Search\OpenSearchAdapter;

catalog_test('CursorPagination encodes and decodes opaque cursor', static function (): void {
    $encoded = CursorPagination::encode(['offset' => 50, 'last' => ['id' => 9]]);
    $decoded = CursorPagination::decode($encoded);
    catalog_assert_same(50, $decoded['offset']);
});

catalog_test('WebhookHmacSigner verifies valid signature within skew window', static function (): void {
    $timestamp = time();
    $body = '{"event":"product.published"}';
    $signature = WebhookHmacSigner::sign('secret', $timestamp, $body);
    catalog_assert_true(WebhookHmacSigner::verify('secret', $timestamp, $body, $signature));
});

catalog_test('SecretCipher round-trips webhook secret encryption', static function (): void {
    $plain = 'whsec_test_value_12345';
    $encrypted = SecretCipher::encrypt($plain);
    catalog_assert_same($plain, SecretCipher::decrypt($encrypted));
});

catalog_test('FileCacheAdapter stores and retrieves values with TTL', static function (): void {
    $dir = sys_get_temp_dir() . '/rateb-catalog-cache-test-' . uniqid('', true);
    $cache = new FileCacheAdapter($dir);
    $cache->set('product:1', ['uuid' => 'p1'], 60);
    $raw = $cache->get('product:1');
    catalog_assert_true($raw !== null && str_contains($raw, 'p1'));
    catalog_assert_true($cache->healthCheck());
});

catalog_test('OpenSearchAdapter healthCheck passes in testing without host', static function (): void {
    $adapter = new OpenSearchAdapter(fallback: new InMemorySearchAdapter());
    catalog_assert_true($adapter->healthCheck());
});

catalog_test('OpenSearchAdapter indexes and searches via in-memory fallback', static function (): void {
    $fallback = new InMemorySearchAdapter();
    $adapter = new OpenSearchAdapter(fallback: $fallback);
    $adapter->indexProduct(['uuid' => 'p-s2', 'name' => 'Sprint Two', 'sku' => 'S2-001', 'barcodes' => []], 'en');
    $result = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('Sprint', 'en', 'product'));
    catalog_assert_true($result->total >= 1);
});
