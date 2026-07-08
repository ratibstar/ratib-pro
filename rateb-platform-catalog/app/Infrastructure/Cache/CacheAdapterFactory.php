<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Cache;

final class CacheAdapterFactory
{
    public static function create(): CacheAdapterInterface
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/cache.php' : dirname(__DIR__, 3) . '/config/cache.php';
        $config = is_file($path) ? require $path : [];
        $adapter = strtolower((string) ($config['CACHE_ADAPTER'] ?? 'file'));

        if ($adapter === 'redis') {
            return new RedisCacheAdapter();
        }

        $cachePath = (string) ($config['CACHE_PATH'] ?? '');
        if ($cachePath === '') {
            $cachePath = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage/cache' : sys_get_temp_dir() . '/rateb-catalog-cache';
        }

        return new FileCacheAdapter($cachePath, (string) ($config['CACHE_PREFIX'] ?? 'cat:'));
    }
}
