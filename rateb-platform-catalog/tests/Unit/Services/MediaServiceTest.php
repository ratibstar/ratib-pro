<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductImageUploaded;
use Rateb\PlatformCatalog\Application\Policies\MediaPolicy;
use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Services\AssetTypeService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\MediaService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\LocalStorageAdapter;

catalog_test('AssetTypeService lists asset types', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $read = new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [[
                'uuid' => 'at1',
                'code' => 'pdf',
                'category' => 'document',
                'is_system' => 1,
                'status' => 'active',
                'name' => 'PDF',
                'resolved_language_code' => $locale->locale,
            ]];
        }
    };

    $service = new AssetTypeService($read, new \Rateb\PlatformCatalog\Application\Policies\AssetTypePolicy(new PermissivePolicyGuard()), new LocaleResolverService());
    $result = $service->list($locale);
    catalog_assert_same(1, count($result['items']));
});

catalog_test('MediaService dispatches ProductImageUploaded event', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-media-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);
    $events = new EventDispatcher();
    $dispatched = false;
    $events->listen('ProductImageUploaded', static function (ProductImageUploaded $event) use (&$dispatched): void {
        $dispatched = true;
        catalog_assert_same('p1', $event->payload()['product_uuid']);
    });

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

    $imageRead = new class implements ProductImageReadRepositoryInterface {
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
                'id' => 1,
                'uuid' => 'img-new',
                'storage_key' => 'catalog/products/p1/images/img-new/original.png',
                'mime_type' => 'image/png',
                'width' => null,
                'height' => null,
                'file_size_bytes' => 4,
                'variant' => 'original',
                'sort_order' => 0,
                'is_primary' => 0,
                'optimized' => 0,
                'checksum_sha256' => hash('sha256', 'test'),
                'asset_type_code' => 'image_original',
            ]];
        }

        public function findByUuidAndVariant(string $imageUuid, string $variant): ?array
        {
            return null;
        }

        public function listTranslationsGrouped(array $imageIds): array
        {
            return [];
        }
    };

    $imageWrite = new class implements ProductImageWriteRepositoryInterface {
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

        public function createForProduct(string $productUuid, string $imageUuid, string $storageKey, array $metadata, array $translations, ?int $actorId = null): string
        {
            return $imageUuid;
        }

        public function removeForProduct(string $productUuid, string $imageUuid, ?int $actorId = null): bool
        {
            return false;
        }
    };

    $service = new MediaService(
        $imageRead,
        $imageWrite,
        $productRead,
        $storage,
        new MediaPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        $events
    );

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $service->uploadImage('p1', [
        'content_base64' => base64_encode((string) $png),
        'mime_type' => 'image/png',
        'extension' => 'png',
    ], null, $locale);

    catalog_assert_true($dispatched);
});

catalog_test('S3CompatibleAdapter is stubbed in Phase 2.6', static function (): void {
    $adapter = new \Rateb\PlatformCatalog\Infrastructure\Storage\S3CompatibleAdapter();
    try {
        $adapter->put('x', 'y');
        throw new RuntimeException('Expected logic exception');
    } catch (LogicException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'not implemented'));
    }
});
