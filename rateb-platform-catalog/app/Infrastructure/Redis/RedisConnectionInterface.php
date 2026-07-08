<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Redis;

interface RedisConnectionInterface
{
    public function ping(): bool;

    public function get(string $key): ?string;

    public function set(string $key, string $value, ?int $ttlSeconds = null): void;

    public function del(string $key): void;

    public function incr(string $key): int;

    public function expire(string $key, int $ttlSeconds): void;

    public function lpush(string $key, string $value): void;

    public function rpop(string $key): ?string;

    public function zadd(string $key, float $score, string $member): void;

    /**
     * @return list<string>
     */
    public function zrangebyscore(string $key, float $min, float $max, int $limit = 1): array;

    public function zrem(string $key, string $member): void;
}
