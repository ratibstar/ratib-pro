<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Mappers\TaxonomyMapper;

catalog_test('TaxonomyMapper builds nested category tree', static function (): void {
    $rows = [
        [
            'id' => 1,
            'uuid' => 'cat-root',
            'parent_id' => null,
            'slug' => 'root',
            'depth' => 0,
            'path' => '/root',
            'sort_order' => 0,
            'image_path' => null,
            'status' => 'active',
            'name' => 'Root',
            'description' => null,
        ],
        [
            'id' => 2,
            'uuid' => 'cat-child',
            'parent_id' => 1,
            'slug' => 'child',
            'depth' => 1,
            'path' => '/root/child',
            'sort_order' => 1,
            'image_path' => null,
            'status' => 'active',
            'name' => 'Child',
            'description' => 'Child desc',
        ],
    ];

    $tree = TaxonomyMapper::buildCategoryTree($rows);
    catalog_assert_same(1, count($tree));
    catalog_assert_same('cat-root', $tree[0]->uuid);
    catalog_assert_same(1, count($tree[0]->children));
    catalog_assert_same('cat-child', $tree[0]->children[0]->uuid);
    catalog_assert_same('cat-root', $tree[0]->children[0]->parentUuid);
});

catalog_test('TaxonomyMapper maps brand row to DTO array', static function (): void {
    $dto = TaxonomyMapper::toBrandDto([
        'uuid' => 'brand-1',
        'slug' => 'acme',
        'logo_path' => null,
        'website' => 'https://example.test',
        'country_code' => 'SA',
        'status' => 'active',
        'name' => 'Acme',
        'description' => 'Brand',
    ]);

    catalog_assert_same('Acme', $dto->toArray()['name']);
});
