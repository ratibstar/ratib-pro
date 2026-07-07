<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class StorageAdapterFactory
{
    public static function create(): StorageAdapterInterface
    {
        $adapter = strtolower((string) (getenv('STORAGE_ADAPTER') ?: 'local'));
        $root = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
            ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
            : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : dirname(__DIR__, 3) . '/storage');

        $cdnBase = defined('RATEB_PLATFORM_CATALOG_CDN_BASE')
            ? (string) RATEB_PLATFORM_CATALOG_CDN_BASE
            : '';

        return match ($adapter) {
            's3' => new S3CompatibleAdapter(),
            default => new LocalStorageAdapter($root, $cdnBase),
        };
    }
}
