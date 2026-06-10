<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class RateLimiter
{
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
}
