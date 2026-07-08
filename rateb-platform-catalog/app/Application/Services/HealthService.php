<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Cache\CacheAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Cache\CacheAdapterInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterInterface;
use Rateb\PlatformCatalog\Core\Database;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlJobQueueWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Storage\S3Config;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory;

final class HealthService
{
    public function __construct(
        private readonly ?SearchAdapterInterface $searchAdapter = null,
        private readonly ?CacheAdapterInterface $cacheAdapter = null,
        private readonly ?QueueAdapterInterface $queueAdapter = null
    ) {
    }

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
            'search' => $this->searchReady(),
            'cache' => $this->cacheReady(),
            'queue' => $this->queueReady(),
        ];

        if ($this->isRedisConfigured()) {
            $checks['redis'] = $this->redisReady();
        }

        $ready = !in_array(false, $checks, true);

        return [
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => gmdate('c'),
        ];
    }

    private function searchReady(): bool
    {
        try {
            return $this->resolveSearchAdapter()->healthCheck();
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheReady(): bool
    {
        try {
            return $this->resolveCacheAdapter()->healthCheck();
        } catch (\Throwable) {
            return false;
        }
    }

    private function queueReady(): bool
    {
        try {
            $adapter = $this->resolveQueueAdapter();
            if (method_exists($adapter, 'healthCheck')) {
                return $adapter->healthCheck();
            }

            return Database::ping(true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            $cache = $this->resolveCacheAdapter();

            return $cache->healthCheck();
        } catch (\Throwable) {
            return false;
        }
    }

    private function isRedisConfigured(): bool
    {
        $queue = strtolower((string) (getenv('QUEUE_ADAPTER') ?: 'database'));
        $cache = strtolower((string) (getenv('CACHE_ADAPTER') ?: 'file'));

        return $queue === 'redis' || $cache === 'redis';
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

    private function resolveSearchAdapter(): SearchAdapterInterface
    {
        return $this->searchAdapter ?? SearchAdapterFactory::create();
    }

    private function resolveCacheAdapter(): CacheAdapterInterface
    {
        return $this->cacheAdapter ?? CacheAdapterFactory::create();
    }

    private function resolveQueueAdapter(): QueueAdapterInterface
    {
        return $this->queueAdapter ?? QueueAdapterFactory::create(new MysqlJobQueueWriteRepository());
    }
}
