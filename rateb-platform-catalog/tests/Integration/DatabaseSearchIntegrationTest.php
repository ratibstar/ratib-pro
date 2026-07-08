<?php

declare(strict_types=1);

catalog_test('Integration: DatabaseSearchAdapter health and search against MariaDB', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: DatabaseSearchAdapter (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $repository = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexReadRepository();
        $adapter = new \Rateb\PlatformCatalog\Infrastructure\Search\DatabaseSearchAdapter($repository);
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    catalog_assert_true($adapter->healthCheck());

    $result = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('', 'en', 'product', [], 'relevance', 5, 0));
    catalog_assert_true($result->total >= 0);
    catalog_assert_true(is_array($result->hits));

    $report = $adapter->reindexLocale('en');
    catalog_assert_true($report->productsIndexed >= 0);
    catalog_assert_true($report->variantsIndexed >= 0);
});

catalog_test('Integration: DatabaseSearchAdapter barcode resolve uses indexed barcodes', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: DatabaseSearchAdapter barcode (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $repository = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexReadRepository();
        $adapter = new \Rateb\PlatformCatalog\Infrastructure\Search\DatabaseSearchAdapter($repository);
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $resolved = $adapter->resolveBarcode('nonexistent-barcode-' . bin2hex(random_bytes(4)), 'en');
    catalog_assert_true($resolved === null);
});
