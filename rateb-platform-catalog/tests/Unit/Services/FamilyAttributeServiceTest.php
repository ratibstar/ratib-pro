<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\AttributePolicy;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\ProductFamilyPolicy;
use Rateb\PlatformCatalog\Application\Services\AttributeService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\ProductFamilyService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AttributeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFamilyReadRepositoryInterface;

catalog_test('ProductFamilyPolicy denies without permission', static function (): void {
    $guard = new ConfigurablePolicyGuard(false);

    try {
        (new ProductFamilyPolicy($guard))->viewList();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same(403, $e->getCode());
    }
});

catalog_test('AttributePolicy allows catalog.attributes.manage', static function (): void {
    $guard = new ConfigurablePolicyGuard(static fn (string $slug): bool => $slug === 'catalog.attributes.manage');

    (new AttributePolicy($guard))->viewList();
    catalog_assert_true(true);
});

catalog_test('ProductFamilyService lists families from read repository', static function (): void {
    $repo = new class implements ProductFamilyReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [[
                'uuid' => 'fam-uuid',
                'code' => 'FAM01',
                'brand_uuid' => null,
                'status' => 'active',
                'name' => 'Family',
                'description' => null,
                'resolved_language_code' => $locale->locale,
            ]];
        }
    };

    $guard = new ConfigurablePolicyGuard(true);

    $service = new ProductFamilyService($repo, new ProductFamilyPolicy($guard), new LocaleResolverService());
    $result = $service->list(10, 0, new LocaleContext('ar', 'en'));

    catalog_assert_same(1, count($result['items']));
    catalog_assert_same('FAM01', $result['items'][0]['code']);
});

catalog_test('AttributeService includes values on detail', static function (): void {
    $repo = new class implements AttributeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return [
                'uuid' => $uuid,
                'code' => 'size',
                'input_type' => 'select',
                'is_variant_defining' => 1,
                'is_filterable' => 1,
                'is_visible' => 1,
                'sort_order' => 0,
                'status' => 'active',
                'name' => 'Size',
                'resolved_language_code' => $locale->locale,
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listValuesForAttribute(string $attributeUuid, LocaleContext $locale): array
        {
            return [[
                'uuid' => 'val-1',
                'sort_order' => 1,
                'status' => 'active',
                'value' => 'L',
                'resolved_language_code' => $locale->locale,
            ]];
        }
    };

    $guard = new ConfigurablePolicyGuard(true);

    $service = new AttributeService($repo, new AttributePolicy($guard), new LocaleResolverService());
    $result = $service->getByUuid('attr-uuid', new LocaleContext('en', 'ar'));

    catalog_assert_same('size', $result['item']['code']);
    catalog_assert_same('L', $result['item']['values'][0]['value']);
});
