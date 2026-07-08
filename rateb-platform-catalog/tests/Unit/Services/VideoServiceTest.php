<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Events\ProductVideoAdded;
use Rateb\PlatformCatalog\Application\Policies\PermissivePolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\VideoPolicy;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\VideoService;
use Rateb\PlatformCatalog\Application\Validators\UploadValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\LocalStorageAdapter;

function video_service_test_product_read(): ProductReadRepositoryInterface
{
    return new class implements ProductReadRepositoryInterface {
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
}

function video_service_test_video_read(string $videoUuid, string $storageKey): ProductVideoReadRepositoryInterface
{
    return new class($videoUuid, $storageKey) implements ProductVideoReadRepositoryInterface {
        public function __construct(
            private readonly string $videoUuid,
            private readonly string $storageKey
        ) {
        }

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
                'uuid' => $this->videoUuid,
                'asset_type_code' => 'video_self_hosted',
                'video_type' => 'self_hosted',
                'external_id' => null,
                'external_url' => null,
                'storage_key' => $this->storageKey,
                'thumbnail_storage_key' => null,
                'duration_seconds' => null,
                'sort_order' => 0,
            ]];
        }

        public function listTranslationsGrouped(array $videoIds): array
        {
            return [];
        }
    };
}

function video_service_test_asset_type_read(): AssetTypeReadRepositoryInterface
{
    return new class implements AssetTypeReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function findByCode(string $code, LocaleContext $locale): ?array
        {
            return [
                'uuid' => 'at-video',
                'code' => $code,
                'category' => 'video',
                'is_system' => 1,
                'status' => 'active',
                'name' => 'Self-hosted video',
                'mime_patterns' => json_encode(['video/mp4', 'video/webm', 'video/quicktime']),
                'extension_patterns' => json_encode(['mp4', 'webm', 'mov']),
            ];
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }
    };
}

catalog_test('VideoService stores base64 self_hosted upload with checksum and storage key', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);

    $captured = ['storage_key' => null, 'video_uuid' => null];
    $videoRead = new class implements ProductVideoReadRepositoryInterface {
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
            return [];
        }

        public function listTranslationsGrouped(array $videoIds): array
        {
            return [];
        }
    };

    $videoWrite = new class($captured) implements ProductVideoWriteRepositoryInterface {
        public function __construct(private array &$captured)
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

        public function createForProduct(
            string $productUuid,
            array $metadata,
            array $translations,
            ?int $actorId = null
        ): string {
            $this->captured['storage_key'] = (string) ($metadata['storage_key'] ?? '');
            $this->captured['video_uuid'] = (string) ($metadata['video_uuid'] ?? '');

            return $this->captured['video_uuid'] !== '' ? $this->captured['video_uuid'] : 'video-new';
        }
    };

    $validator = new UploadValidator(video_service_test_asset_type_read());
    $service = new VideoService(
        $videoRead,
        $videoWrite,
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        $validator
    );

    $content = str_repeat('A', 64);
    $service->create('p1', [
        'video_type' => 'self_hosted',
        'content_base64' => base64_encode($content),
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
    ], null, $locale);

    catalog_assert_true($captured['storage_key'] !== '');
    catalog_assert_true(str_contains($captured['storage_key'], 'catalog/products/p1/videos/'));
    catalog_assert_true(str_ends_with($captured['storage_key'], '.mp4'));
    catalog_assert_true($storage->exists($captured['storage_key']));
});

catalog_test('VideoService stores multipart self_hosted upload', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-mp-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);

    $tmp = tempnam(sys_get_temp_dir(), 'vid');
    file_put_contents($tmp, str_repeat('B', 32));

    $captured = ['storage_key' => null];
    $videoWrite = new class($captured) implements ProductVideoWriteRepositoryInterface {
        public function __construct(private array &$captured)
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

        public function createForProduct(
            string $productUuid,
            array $metadata,
            array $translations,
            ?int $actorId = null
        ): string {
            $this->captured['storage_key'] = (string) ($metadata['storage_key'] ?? '');

            return (string) ($metadata['video_uuid'] ?? 'video-mp');
        }
    };

    $service = new VideoService(
        new class implements ProductVideoReadRepositoryInterface {
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
                return [];
            }

            public function listTranslationsGrouped(array $videoIds): array
            {
                return [];
            }
        },
        $videoWrite,
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    $service->create('p1', [
        'video_type' => 'self_hosted',
    ], [
        'name' => 'clip.mp4',
        'type' => 'video/mp4',
        'tmp_name' => $tmp,
        'error' => UPLOAD_ERR_OK,
        'size' => 32,
    ], $locale);

    catalog_assert_true($captured['storage_key'] !== '');
    catalog_assert_true($storage->exists($captured['storage_key']));

    @unlink($tmp);
});

catalog_test('VideoService rejects validation failure for self_hosted upload', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-bad-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);

    $service = new VideoService(
        video_service_test_video_read('x', ''),
        new class implements ProductVideoWriteRepositoryInterface {
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

            public function createForProduct(
                string $productUuid,
                array $metadata,
                array $translations,
                ?int $actorId = null
            ): string {
                throw new RuntimeException('write should not be called');
            }
        },
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    try {
        $service->create('p1', [
            'video_type' => 'self_hosted',
            'content_base64' => base64_encode('bad'),
            'mime_type' => 'application/x-msdownload',
            'extension' => 'exe',
        ], null, $locale);
        throw new RuntimeException('Expected validation failure');
    } catch (InvalidArgumentException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'Executable'));
    }
});

catalog_test('VideoService rejects missing binary and storage_key for self_hosted', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $storage = new LocalStorageAdapter(sys_get_temp_dir() . '/rateb-video-miss-' . bin2hex(random_bytes(4)));

    $service = new VideoService(
        video_service_test_video_read('x', ''),
        new class implements ProductVideoWriteRepositoryInterface {
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

            public function createForProduct(
                string $productUuid,
                array $metadata,
                array $translations,
                ?int $actorId = null
            ): string {
                throw new RuntimeException('write should not be called');
            }
        },
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    try {
        $service->create('p1', ['video_type' => 'self_hosted'], null, $locale);
        throw new RuntimeException('Expected missing binary failure');
    } catch (InvalidArgumentException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'storage_key or upload binary'));
    }
});

catalog_test('VideoService accepts metadata-only self_hosted with existing storage_key', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-meta-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);
    $existingKey = 'catalog/products/p1/videos/preupload/clip.mp4';
    $storage->put($existingKey, 'preuploaded', ['mime_type' => 'video/mp4']);

    $captured = ['storage_key' => null];
    $videoWrite = new class($captured) implements ProductVideoWriteRepositoryInterface {
        public function __construct(private array &$captured)
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

        public function createForProduct(
            string $productUuid,
            array $metadata,
            array $translations,
            ?int $actorId = null
        ): string {
            $this->captured['storage_key'] = (string) ($metadata['storage_key'] ?? '');

            return 'video-meta';
        }
    };

    $service = new VideoService(
        video_service_test_video_read('video-meta', $existingKey),
        $videoWrite,
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    $service->create('p1', [
        'video_type' => 'self_hosted',
        'storage_key' => $existingKey,
    ], null, $locale);

    catalog_assert_same($existingKey, $captured['storage_key']);
});

catalog_test('VideoService rolls back storage when repository persistence fails', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-rollback-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);

    $service = new VideoService(
        video_service_test_video_read('x', ''),
        new class implements ProductVideoWriteRepositoryInterface {
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

            public function createForProduct(
                string $productUuid,
                array $metadata,
                array $translations,
                ?int $actorId = null
            ): string {
                throw new RuntimeException('database write failed');
            }
        },
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    try {
        $service->create('p1', [
            'video_type' => 'self_hosted',
            'content_base64' => base64_encode(str_repeat('V', 32)),
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
        ], null, $locale);
        throw new RuntimeException('Expected repository failure');
    } catch (RuntimeException $e) {
        catalog_assert_same('database write failed', $e->getMessage());
    }

    $storedFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $fileCount = 0;
    foreach ($storedFiles as $file) {
        if ($file->isFile()) {
            $fileCount++;
        }
    }
    catalog_assert_same(0, $fileCount);
});

catalog_test('VideoService rejects binary upload combined with storage_key', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $storage = new LocalStorageAdapter(sys_get_temp_dir() . '/rateb-video-both-' . bin2hex(random_bytes(4)));

    $service = new VideoService(
        video_service_test_video_read('x', ''),
        new class implements ProductVideoWriteRepositoryInterface {
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

            public function createForProduct(
                string $productUuid,
                array $metadata,
                array $translations,
                ?int $actorId = null
            ): string {
                throw new RuntimeException('write should not be called');
            }
        },
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        new EventDispatcher(),
        new UploadValidator(video_service_test_asset_type_read())
    );

    try {
        $service->create('p1', [
            'video_type' => 'self_hosted',
            'storage_key' => 'catalog/products/p1/videos/pre/clip.mp4',
            'content_base64' => base64_encode('data'),
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
        ], null, $locale);
        throw new RuntimeException('Expected conflict failure');
    } catch (InvalidArgumentException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'must not be supplied'));
    }
});

catalog_test('VideoService dispatches ProductVideoAdded event', static function (): void {
    $locale = new LocaleContext('en', 'ar');
    $root = sys_get_temp_dir() . '/rateb-video-event-' . bin2hex(random_bytes(4));
    mkdir($root, 0755, true);
    $storage = new LocalStorageAdapter($root);
    $existingKey = 'catalog/products/p1/videos/ext/clip.mp4';
    $storage->put($existingKey, 'video', ['mime_type' => 'video/mp4']);

    $events = new EventDispatcher();
    $dispatched = false;
    $events->listen('ProductVideoAdded', static function (ProductVideoAdded $event) use (&$dispatched): void {
        $dispatched = true;
        catalog_assert_same('self_hosted', $event->payload()['video_type']);
    });

    $service = new VideoService(
        video_service_test_video_read('video-event', $existingKey),
        new class implements ProductVideoWriteRepositoryInterface {
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

            public function createForProduct(
                string $productUuid,
                array $metadata,
                array $translations,
                ?int $actorId = null
            ): string {
                return 'video-event';
            }
        },
        video_service_test_product_read(),
        $storage,
        new VideoPolicy(new PermissivePolicyGuard()),
        new LocaleResolverService(),
        $events,
        new UploadValidator(video_service_test_asset_type_read())
    );

    $service->create('p1', [
        'video_type' => 'self_hosted',
        'storage_key' => $existingKey,
    ], null, $locale);

    catalog_assert_true($dispatched);
});
