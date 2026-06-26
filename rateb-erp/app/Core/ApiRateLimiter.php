<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/** File-backed API rate limiting (no Redis). */
final class ApiRateLimiter
{
    public static function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $dir = $root . '/storage/rate-limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/api_' . hash('sha256', $key) . '.json';
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

    public static function allowRequest(string $method, ?string $bearerToken = null): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $tokenKey = $bearerToken !== null && $bearerToken !== ''
            ? hash('sha256', $bearerToken)
            : 'guest';
        $isWrite = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $max = $isWrite ? 60 : 300;
        $decay = 60;
        $bucket = ($isWrite ? 'write' : 'read') . '_' . $tokenKey . '_' . md5($ip);
        return self::attempt($bucket, $max, $decay);
    }
}
