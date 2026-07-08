<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Redis;

final class RedisConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $password,
        public readonly int $database,
        public readonly string $prefix,
        public readonly float $timeout
    ) {
    }

    public static function fromEnvironment(): self
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/redis.php' : dirname(__DIR__, 3) . '/config/redis.php';
        $config = is_file($path) ? require $path : [];

        return new self(
            host: (string) ($config['REDIS_HOST'] ?? '127.0.0.1'),
            port: (int) ($config['REDIS_PORT'] ?? 6379),
            password: isset($config['REDIS_PASSWORD']) && $config['REDIS_PASSWORD'] !== '' ? (string) $config['REDIS_PASSWORD'] : null,
            database: (int) ($config['REDIS_DATABASE'] ?? 0),
            prefix: (string) ($config['REDIS_PREFIX'] ?? 'catalog:'),
            timeout: (float) ($config['REDIS_TIMEOUT'] ?? 2.0)
        );
    }
}
