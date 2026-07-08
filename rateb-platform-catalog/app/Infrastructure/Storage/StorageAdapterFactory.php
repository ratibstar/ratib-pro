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

        $signedUrlsEnabled = self::signedUrlsEnabled();
        $signedUrlGenerator = null;
        if ($signedUrlsEnabled) {
            $secret = (string) (getenv('SIGNED_URL_SECRET') ?: '');
            $signedUrlGenerator = new SignedUrlGenerator($secret, $cdnBase);
        }

        if ($adapter === 's3' && self::s3Enabled()) {
            return S3CompatibleAdapter::fromConfig();
        }

        return new LocalStorageAdapter($root, $cdnBase, $signedUrlsEnabled, $signedUrlGenerator);
    }

    public static function s3Enabled(): bool
    {
        return S3Config::fromEnvironment()->enabled;
    }

    public static function signedUrlsEnabled(): bool
    {
        return filter_var(getenv('CATALOG_SIGNED_URLS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }
}
