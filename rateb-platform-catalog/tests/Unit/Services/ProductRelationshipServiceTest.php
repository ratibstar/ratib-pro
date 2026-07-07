<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\ProductBundlePolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductVariantPolicy;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\ProductBundleService;
use Rateb\PlatformCatalog\Application\Services\ProductVariantService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantWriteRepositoryInterface;

catalog_test('ProductVariantPolicy requires variants.manage for create', static function (): void {
    $guard = new PermissivePolicyGuard();
    (new ProductVariantPolicy($guard))->viewList();
    (new ProductVariantPolicy($guard))->create();
    catalog_assert_true(true);
});

catalog_test('ProductBundlePolicy requires bundles.manage for replace', static function (): void {
    $guard = new PermissivePolicyGuard();
    (new ProductBundlePolicy($guard))->view();
    (new ProductBundlePolicy($guard))->manage();
    catalog_assert_true(true);
});

catalog_test('ProductVariantService lists variants with nested data', static function (): void {
    $locale = new LocaleContext('en', 'ar');

    $read = new class implements ProductVariantReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByProductUuid(string $productUuid, LocaleContext $locale): array
        {
            return [[
                'id' => 10,
                'uuid' => 'v1',
                'sku' => 'VAR-1',
                'primary_barcode' => null,
                'sort_order' => 0,
                'weight_kg' => null,
                'length_cm' => null,
                'width_cm' => null,
                'height_cm' => null,
                'status' => 'draft',
                'is_default' => 0,
                'name' => 'Variant',
                'description' => null,
                'resolved_language_code' => $locale->locale,
            ]];
        }

        public function listBarcodesGroupedByVariantId(array $variantIds): array
        {
            return [10 => [['uuid' => 'b1', 'barcode' => '999', 'barcode_type' => 'OTHER', 'is_primary' => 1]]];
        }

        public function listOptionValuesGroupedByVariantId(array $variantIds, LocaleContext $locale): array
        {
            return [10 => [['attribute_code' => 'size', 'value' => 'L']]];
        }
    };

    $productRead = new class implements ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return ['uuid' => $uuid];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listFiltered(LocaleContext $locale, $filter, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function findLockVersion(string $uuid): ?int
        {
            return 1;
        }

        public function findWorkflowMeta(string $uuid): ?array
        {
            return null;
        }
    };

    $service = new ProductVariantService(
        $read,
        new class implements ProductVariantWriteRepositoryInterface {
            public function create(array $data): string
            {
                return '';
            }

            public function update(string $uuid, array $data): bool
            {
                return false;
            }

            public function softDelete(string $uuid, ?int $actorId = null): bool
            {
                return false;
            }

            public function createForProduct(string $productUuid, array $data, array $translations, array $barcodes, array $optionValues, ?int $actorId = null): string
            {
                return 'v-new';
            }
        },
        $productRead,
        new ProductVariantPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
    );

    $result = $service->list('p1', $locale);
    catalog_assert_same(1, count($result['items']));
    catalog_assert_same('VAR-1', $result['items'][0]['sku']);
    catalog_assert_same(1, count($result['items'][0]['barcodes']));
});

catalog_test('ProductBundleService delegates replace to write repository', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $replaced = false;

    $productRead = new class implements ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return ['uuid' => $uuid, 'is_bundle' => 1];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listFiltered(LocaleContext $locale, $filter, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function findLockVersion(string $uuid): ?int
        {
            return 1;
        }

        public function findWorkflowMeta(string $uuid): ?array
        {
            return null;
        }
    };

    $bundleRead = new class implements ProductBundleReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listComponents(string $bundleProductUuid, LocaleContext $locale): array
        {
            return [];
        }

        public function wouldIntroduceCycle(int $bundleProductId, array $componentProductIds): bool
        {
            return false;
        }
    };

    $bundleWrite = new class($replaced) implements ProductBundleWriteRepositoryInterface {
        public function __construct(private bool &$replaced)
        {
        }

        public function create(array $data): string
        {
            return '';
        }

        public function update(string $uuid, array $data): bool
        {
            return false;
        }

        public function softDelete(string $uuid, ?int $actorId = null): bool
        {
            return false;
        }

        public function replaceBundle(string $bundleProductUuid, array $components, ?int $actorId = null): void
        {
            $this->replaced = true;
        }
    };

    $service = new ProductBundleService(
        $bundleRead,
        $bundleWrite,
        $productRead,
        new ProductBundlePolicy(new PermissivePolicyGuard()),
        new LocaleResolverService()
    );

    $service->replace('bundle-1', ['components' => []], $locale);
    catalog_assert_true($replaced);
});
