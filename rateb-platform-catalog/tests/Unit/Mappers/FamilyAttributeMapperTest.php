<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\FamilyAttributeMapper;

catalog_test('FamilyAttributeMapper maps product family row', static function (): void {
    $dto = FamilyAttributeMapper::toProductFamilyDto([
        'uuid' => 'fam-1',
        'code' => 'SMARTPHONES',
        'brand_uuid' => 'brand-1',
        'status' => 'active',
        'name' => 'هواتف ذكية',
        'description' => 'وصف',
    ]);

    catalog_assert_same('SMARTPHONES', $dto->toArray()['code']);
    catalog_assert_same('brand-1', $dto->toArray()['brand_uuid']);
});

catalog_test('FamilyAttributeMapper maps attribute with values', static function (): void {
    $dto = FamilyAttributeMapper::toAttributeDto([
        'uuid' => 'attr-1',
        'code' => 'color',
        'input_type' => 'select',
        'is_variant_defining' => 1,
        'is_filterable' => 1,
        'is_visible' => 1,
        'sort_order' => 10,
        'status' => 'active',
        'name' => 'Color',
    ], [
        [
            'uuid' => 'val-1',
            'sort_order' => 1,
            'status' => 'active',
            'value' => 'Red',
        ],
    ]);

    $array = $dto->toArray();
    catalog_assert_same('color', $array['code']);
    catalog_assert_true($array['is_variant_defining']);
    catalog_assert_same(1, count($array['values']));
    catalog_assert_same('Red', $array['values'][0]['value']);
});
