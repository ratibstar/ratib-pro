<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class IdempotencyResponseCachePolicy
{
    /** @var list<int> */
    private const CACHEABLE_STATUSES = [200, 201, 202, 204];

    public static function shouldCache(int $status): bool
    {
        return in_array($status, self::CACHEABLE_STATUSES, true);
    }
}
