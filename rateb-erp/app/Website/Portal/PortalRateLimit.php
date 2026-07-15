<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\SessionManager;

/**
 * Phase WEBSITE-08 — Lightweight portal rate limiting (session bucket; WebsiteKernel only).
 */
final class PortalRateLimit
{
    public static function allow(string $action, int $max = 30, int $windowSeconds = 60): bool
    {
        $key = 'rateb_portal_rl_' . preg_replace('/[^a-z0-9_]/i', '', $action);
        $now = time();
        $bucket = SessionManager::get($key, null);
        if (!is_array($bucket) || (int) ($bucket['reset'] ?? 0) < $now) {
            SessionManager::set($key, ['count' => 1, 'reset' => $now + $windowSeconds]);

            return true;
        }
        $count = (int) ($bucket['count'] ?? 0) + 1;
        if ($count > $max) {
            return false;
        }
        SessionManager::set($key, ['count' => $count, 'reset' => (int) $bucket['reset']]);

        return true;
    }
}
