<?php

declare(strict_types=1);

catalog_test('Integration: Meilisearch health and document roundtrip', static function (): void {
    if (!catalog_adapter_tests_enabled('meilisearch')) {
        echo "[SKIP] Adapter: Meilisearch (set CATALOG_ADAPTER_TESTS=meilisearch)\n";

        return;
    }

    $host = getenv('MEILISEARCH_HOST') ?: '';
    if ($host === '') {
        echo "[SKIP] Adapter: MEILISEARCH_HOST not configured\n";

        return;
    }

    $adapter = new \Rateb\PlatformCatalog\Infrastructure\Search\MeilisearchAdapter(
        $host,
        getenv('MEILISEARCH_API_KEY') ?: null
    );

    if (!$adapter->healthCheck()) {
        echo "[SKIP] Adapter: Meilisearch not reachable at {$host}\n";

        return;
    }

    $productUuid = 'integration-' . bin2hex(random_bytes(4));
    $adapter->indexProduct([
        'uuid' => $productUuid,
        'name' => 'Integration Product',
        'sku' => 'INT-SKU',
        'barcodes' => ['99112233'],
        'boost_score' => 1,
        'category_id' => 'c1',
    ], 'en');

    $resolved = $adapter->resolveBarcode('99112233', 'en');
    catalog_assert_true($resolved !== null);

    $result = $adapter->search(new \Rateb\PlatformCatalog\Infrastructure\Search\SearchQuery('Integration', 'en', 'product'));
    catalog_assert_true($result->total >= 1);

    $adapter->deleteProduct($productUuid, 'en');
});
