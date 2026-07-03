<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class IpRateLimiter
{
    public static function isLimited(string $key, int $maxAttempts): bool
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $dir = $root . '/storage/rate-limit';
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        if (!is_file($file)) {
            return false;
        }
        $raw = @file_get_contents($file);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return false;
        }
        $now = time();
        if ($now > (int) ($decoded['reset'] ?? 0)) {
            return false;
        }

        return (int) ($decoded['count'] ?? 0) >= $maxAttempts;
    }

    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $dir = $root . '/storage/rate-limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $now = time();
        $bucket = ['count' => 0, 'reset' => $now + $decaySeconds];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $bucket = $decoded;
            }
        }
        if ($now > (int) ($bucket['reset'] ?? 0)) {
            $bucket = ['count' => 0, 'reset' => $now + $decaySeconds];
        }
        if ((int) ($bucket['count'] ?? 0) >= $maxAttempts) {
            @file_put_contents($file, json_encode($bucket), LOCK_EX);
            return false;
        }
        $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
        @file_put_contents($file, json_encode($bucket), LOCK_EX);
        return true;
    }

    public static function reset(string $key): void
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $file = $root . '/storage/rate-limit/' . hash('sha256', $key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
