<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Core\Database;
use Rateb\PlatformCatalog\Infrastructure\Storage\S3Config;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory;

final class HealthService
{
    public function liveness(): array
    {
        return [
            'status' => 'ok',
            'service' => 'rateb-platform-catalog',
            'version' => defined('RATEB_PLATFORM_CATALOG_VERSION') ? RATEB_PLATFORM_CATALOG_VERSION : '1.3.1',
            'release' => defined('RATEB_PLATFORM_CATALOG_RELEASE') ? RATEB_PLATFORM_CATALOG_RELEASE : null,
            'phase' => defined('RATEB_PLATFORM_CATALOG_PHASE') ? RATEB_PLATFORM_CATALOG_PHASE : null,
            'architecture_version' => defined('RATEB_PLATFORM_CATALOG_ARCHITECTURE_VERSION')
                ? RATEB_PLATFORM_CATALOG_ARCHITECTURE_VERSION
                : null,
            'build_timestamp' => defined('RATEB_PLATFORM_CATALOG_BUILD_TIMESTAMP')
                ? RATEB_PLATFORM_CATALOG_BUILD_TIMESTAMP
                : null,
            'timestamp' => gmdate('c'),
        ];
    }

    public function readiness(): array
    {
        $checks = [
            'database' => Database::ping(true),
            'storage' => $this->storageReady(),
        ];

        $ready = !in_array(false, $checks, true);

        return [
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => gmdate('c'),
        ];
    }

    private function storageReady(): bool
    {
        $adapter = strtolower((string) (getenv('STORAGE_ADAPTER') ?: 'local'));
        if ($adapter === 's3' && StorageAdapterFactory::s3Enabled()) {
            return $this->s3Configured();
        }

        return $this->localStorageWritable();
    }

    private function s3Configured(): bool
    {
        try {
            S3Config::fromEnvironment()->validate();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function localStorageWritable(): bool
    {
        $storageRoot = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
            ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
            : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : '');

        if ($storageRoot === '' || !is_dir($storageRoot)) {
            return false;
        }

        $probe = $storageRoot . '/.ready_probe';
        if (@file_put_contents($probe, '1') === false) {
            return false;
        }

        @unlink($probe);

        return true;
    }
}
