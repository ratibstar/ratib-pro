<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\SearchPolicy;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\SearchQueryService;
use Rateb\PlatformCatalog\Infrastructure\Search\InMemorySearchAdapter;

catalog_test('SearchQueryService searches products', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct(['uuid' => 'p1', 'name' => 'Widget', 'boost_score' => 1], 'en');

    $service = new SearchQueryService($adapter, new SearchPolicy(new PermissivePolicyGuard()), new LocaleResolverService());
    $result = $service->searchProducts(['q' => 'widget'], new LocaleContext('en', 'ar'));

    catalog_assert_same(1, count($result['items']));
    catalog_assert_same(1, $result['meta']['total']);
});

catalog_test('SearchQueryService resolves barcode', static function (): void {
    $adapter = new InMemorySearchAdapter();
    $adapter->indexProduct(['uuid' => 'p1', 'name' => 'Widget', 'barcodes' => ['999'], 'boost_score' => 1], 'en');

    $service = new SearchQueryService($adapter, new SearchPolicy(new PermissivePolicyGuard()), new LocaleResolverService());
    $result = $service->resolveBarcode('999', new LocaleContext('en', 'ar'));

    catalog_assert_true($result['item'] !== null);
    catalog_assert_same('product', $result['item']['match_type']);
});
