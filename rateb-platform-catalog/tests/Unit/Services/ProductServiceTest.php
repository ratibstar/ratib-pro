<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\DTO\ProductListFilter;
use Rateb\PlatformCatalog\Application\Policies\ProductPolicy;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Services\ProductService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface;

catalog_test('ProductPolicy enforces create permission', static function (): void {
    $guard = new ConfigurablePolicyGuard(static fn (string $slug): bool => $slug === 'catalog.products.view');

    (new ProductPolicy($guard))->viewList();

    try {
        (new ProductPolicy($guard))->create();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same(403, $e->getCode());
    }
});

catalog_test('ProductService lists products from read repository', static function (): void {
    $read = new class implements ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return $this->listFiltered($locale, new ProductListFilter(), $limit, $offset);
        }

        public function listFiltered(LocaleContext $locale, ProductListFilter $filter, int $limit = 100, int $offset = 0): array
        {
            return [[
                'uuid' => 'p1',
                'sku' => 'SKU-1',
                'brand_uuid' => null,
                'category_uuid' => 'c1',
                'family_uuid' => null,
                'unit_uuid' => 'u1',
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
                'lock_version' => 1,
                'publish_at' => null,
                'archive_at' => null,
                'published_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'search_weight' => '1.0000',
                'boost_score' => '0.0000',
                'name' => 'Product',
                'short_description' => null,
                'description' => null,
                'resolved_language_code' => $locale->locale,
            ]];
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

    $write = new class implements ProductWriteRepositoryInterface {
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

        public function createWithTranslations(array $productData, array $translations, ?int $actorId = null): string
        {
            return 'new';
        }

        public function updateWithTranslations(string $uuid, array $productData, array $translations, int $expectedLockVersion, ?int $actorId = null): int
        {
            return 2;
        }
    };

    $guard = new ConfigurablePolicyGuard(true);

    $service = new ProductService($read, $write, new ProductPolicy($guard), new LocaleResolverService(), new ConcurrencyService(), new EventDispatcher());
    $result = $service->list(new ProductListFilter(), 10, 0, new LocaleContext('ar', 'en'));

    catalog_assert_same(1, count($result['items']));
    catalog_assert_same('SKU-1', $result['items'][0]['sku']);
});

catalog_test('ProductService update propagates version conflict', static function (): void {
    $read = new class implements ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listFiltered(LocaleContext $locale, ProductListFilter $filter, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function findLockVersion(string $uuid): ?int
        {
            return 4;
        }

        public function findWorkflowMeta(string $uuid): ?array
        {
            return null;
        }
    };

    $write = new class implements ProductWriteRepositoryInterface {
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

        public function createWithTranslations(array $productData, array $translations, ?int $actorId = null): string
        {
            return '';
        }

        public function updateWithTranslations(string $uuid, array $productData, array $translations, int $expectedLockVersion, ?int $actorId = null): int
        {
            throw new RuntimeException('version_conflict', 409);
        }
    };

    $guard = new ConfigurablePolicyGuard(true);

    $service = new ProductService($read, $write, new ProductPolicy($guard), new LocaleResolverService(), new ConcurrencyService(), new EventDispatcher());

    try {
        $service->update('p1', ['sku' => 'SKU-2'], 2);
        throw new RuntimeException('Expected conflict');
    } catch (ProductVersionConflictException $e) {
        catalog_assert_same(4, $e->currentLockVersion);
    }
});
