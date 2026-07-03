<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class RateLimiter
{
    public static function isLimited(string $key, int $maxAttempts): bool
    {
        $now = time();
        $bucket = $_SESSION['_rate_limit'][$key] ?? ['count' => 0, 'reset' => $now];

        if ($now > (int) ($bucket['reset'] ?? 0)) {
            return false;
        }

        return (int) ($bucket['count'] ?? 0) >= $maxAttempts;
    }

    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $now = time();
        $bucket = $_SESSION['_rate_limit'][$key] ?? ['count' => 0, 'reset' => $now + $decaySeconds];

        if ($now > (int) $bucket['reset']) {
            $bucket = ['count' => 0, 'reset' => $now + $decaySeconds];
        }

        if ((int) $bucket['count'] >= $maxAttempts) {
            $_SESSION['_rate_limit'][$key] = $bucket;
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        $_SESSION['_rate_limit'][$key] = $bucket;
        return true;
    }

    public static function reset(string $key): void
    {
        unset($_SESSION['_rate_limit'][$key]);
    }
}
