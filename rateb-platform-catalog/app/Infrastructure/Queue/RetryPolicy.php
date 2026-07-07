<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue;

final class RetryPolicy
{
    public static function delaySecondsForAttempt(int $attempt): int
    {
        if ($attempt <= 1) {
            return 0;
        }

        return min(3600, (int) (30 * (2 ** ($attempt - 2))));
    }
}
