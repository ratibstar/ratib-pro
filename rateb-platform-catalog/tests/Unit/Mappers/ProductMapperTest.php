<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Mappers\ProductMapper;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;

catalog_test('ProductMapper maps product row with lock_version', static function (): void {
    $dto = ProductMapper::toProductDto([
        'uuid' => 'prod-1',
        'sku' => 'SKU-001',
        'brand_uuid' => 'brand-1',
        'category_uuid' => 'cat-1',
        'family_uuid' => null,
        'unit_uuid' => 'unit-1',
        'is_bundle' => 0,
        'primary_barcode' => null,
        'weight_kg' => null,
        'length_cm' => null,
        'width_cm' => null,
        'height_cm' => null,
        'manufacturer_id' => null,
        'country_id' => null,
        'warranty_months' => null,
        'tax_class' => null,
        'status' => 'draft',
        'version_number' => 1,
        'lock_version' => 3,
        'publish_at' => null,
        'archive_at' => null,
        'published_at' => null,
        'approved_by' => null,
        'approved_at' => null,
        'search_weight' => '1.0000',
        'boost_score' => '0.0000',
        'name' => 'منتج',
        'short_description' => 'وصف قصير',
        'description' => null,
    ]);

    catalog_assert_same(3, $dto->toArray()['lock_version']);
    catalog_assert_same('draft', $dto->toArray()['status']);
});

catalog_test('ConcurrencyService formats and parses ETag', static function (): void {
    $service = new ConcurrencyService();
    catalog_assert_same('W/"7"', $service->formatEtag(7));
    catalog_assert_same(7, $service->parseIfMatch('W/"7"'));
    catalog_assert_same(7, $service->resolveExpectedLockVersion(7));
});

catalog_test('ConcurrencyService throws on lock mismatch', static function (): void {
    $service = new ConcurrencyService();

    try {
        $service->assertLockVersion(2, 5);
        throw new RuntimeException('Expected conflict');
    } catch (\Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException $e) {
        catalog_assert_same(5, $e->currentLockVersion);
        catalog_assert_same(409, $e->getCode());
    }
});
