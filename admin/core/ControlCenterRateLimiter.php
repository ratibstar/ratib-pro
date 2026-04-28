<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/ControlCenterRateLimiter.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/ControlCenterRateLimiter.php`.
 */
declare(strict_types=1);

final class ControlCenterRateLimiter
{
    private const SESSION_KEY = 'cc_rate';

    /**
     * @return true if allowed, false if rate limited
     */
    public static function check(string $bucket, string $identity, int $maxHits, int $windowSeconds): bool
    {
        if ($identity === '') {
            $identity = 'anon';
        }
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $now = microtime(true);
        $key = $bucket . ':' . $identity;
        if (!isset($_SESSION[self::SESSION_KEY][$key]) || !is_array($_SESSION[self::SESSION_KEY][$key])) {
            $_SESSION[self::SESSION_KEY][$key] = ['hits' => [], 'blocked_until' => 0];
        }
        $state = &$_SESSION[self::SESSION_KEY][$key];
        if (($state['blocked_until'] ?? 0) > $now) {
            return false;
        }
        $cutoff = $now - $windowSeconds;
        $state['hits'] = array_values(array_filter(
            $state['hits'],
            static function ($t) use ($cutoff) {
                return is_float($t) && $t >= $cutoff;
            }
        ));
        if (count($state['hits']) >= $maxHits) {
            $state['blocked_until'] = $now + min(30, $windowSeconds);
            return false;
        }
        $state['hits'][] = $now;
        return true;
    }
}
