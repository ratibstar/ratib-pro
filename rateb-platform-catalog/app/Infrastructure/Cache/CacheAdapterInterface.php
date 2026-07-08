<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Cache;

interface CacheAdapterInterface
{
    public function get(string $key): ?string;

  /**
     * @param array<string, mixed>|null $value
     */
    public function set(string $key, ?array $value, int $ttlSeconds): void;

    public function delete(string $key): void;

    public function healthCheck(): bool;
}
