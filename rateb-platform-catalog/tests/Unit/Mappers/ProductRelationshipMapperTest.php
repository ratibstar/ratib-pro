<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Mappers\ProductRelationshipMapper;

catalog_test('ProductRelationshipMapper maps variant with barcodes and options', static function (): void {
    $dto = ProductRelationshipMapper::toVariantDto(
        [
            'uuid' => 'v1',
            'sku' => 'VAR-1',
            'primary_barcode' => '111',
            'sort_order' => 1,
            'weight_kg' => '1.5000',
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'status' => 'draft',
            'is_default' => 1,
            'name' => 'Red / Large',
            'description' => null,
        ],
        [['uuid' => 'b1', 'barcode' => '111', 'barcode_type' => 'EAN13', 'is_primary' => 1]],
        [['attribute_code' => 'color', 'value' => 'Red']]
    );

    $array = $dto->toArray();
    catalog_assert_same('v1', $array['uuid']);
    catalog_assert_same('VAR-1', $array['sku']);
    catalog_assert_same(1, count($array['barcodes']));
    catalog_assert_same(1, count($array['option_values']));
});

catalog_test('ProductRelationshipMapper maps bundle component', static function (): void {
    $dto = ProductRelationshipMapper::toBundleComponentDto([
        'uuid' => 'bc1',
        'component_product_uuid' => 'p2',
        'component_variant_uuid' => 'v2',
        'quantity' => '2.0000',
        'sort_order' => 0,
        'is_optional' => 0,
        'component_name' => 'Widget',
        'component_sku' => 'W-1',
    ]);

    catalog_assert_same('p2', $dto->toArray()['component_product_uuid']);
    catalog_assert_same('2.0000', $dto->toArray()['quantity']);
});

catalog_test('ProductRelationshipMapper maps product relation', static function (): void {
    $dto = ProductRelationshipMapper::toRelationDto([
        'uuid' => 'r1',
        'related_product_uuid' => 'p9',
        'relation_type' => 'upsell',
        'sort_order' => 3,
        'is_bidirectional' => 1,
        'related_product_name' => 'Accessory',
        'related_product_sku' => 'ACC-1',
    ]);

    catalog_assert_same('upsell', $dto->toArray()['relation_type']);
    catalog_assert_true($dto->toArray()['is_bidirectional']);
});
