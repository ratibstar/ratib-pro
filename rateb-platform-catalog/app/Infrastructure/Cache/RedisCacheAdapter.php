<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Cache;

use Rateb\PlatformCatalog\Infrastructure\Redis\RedisClient;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisConfig;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisConnectionInterface;

final class RedisCacheAdapter implements CacheAdapterInterface
{
    private readonly RedisConnectionInterface $client;

    public function __construct(?RedisConnectionInterface $client = null)
    {
        $this->client = $client ?? new RedisClient(RedisConfig::fromEnvironment());
    }

    public function get(string $key): ?string
    {
        return $this->client->get($key);
    }

    public function set(string $key, ?array $value, int $ttlSeconds): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'null';
        $this->client->set($key, $encoded, max(1, $ttlSeconds));
    }

    public function delete(string $key): void
    {
        $this->client->del($key);
    }

    public function healthCheck(): bool
    {
        return $this->client->ping();
    }
}
