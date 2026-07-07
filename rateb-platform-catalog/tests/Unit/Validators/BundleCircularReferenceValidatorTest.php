<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Validators\BundleCircularReferenceValidator;

catalog_test('BundleCircularReferenceValidator rejects self-reference', static function (): void {
    $validator = new BundleCircularReferenceValidator();

    try {
        $validator->assertNoCircularReference('bundle-1', [
            ['component_product_uuid' => 'bundle-1'],
        ]);
        throw new RuntimeException('Expected invalid argument');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('Bundle cannot contain itself', $e->getMessage());
    }
});

catalog_test('BundleCircularReferenceValidator accepts distinct components', static function (): void {
    $validator = new BundleCircularReferenceValidator();
    $validator->assertNoCircularReference('bundle-1', [
        ['component_product_uuid' => 'component-a'],
        ['component_product_uuid' => 'component-b'],
    ]);
    catalog_assert_true(true);
});
