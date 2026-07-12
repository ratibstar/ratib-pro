<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase C — Hybrid Sync feature gates (env/constants only; never HTTP).
 * Reuses offline sync-policy numbers as defaults.
 */
final class HybridSyncConfig
{
    public const MAX_RETRIES = 5;
    /** @var list<int> */
    public const BACKOFF_SECONDS = [30, 60, 120, 300, 600];
    public const BATCH_SIZE = 50;

    public static function enabled(): bool
    {
        if (!HybridRuntime::shouldUseSqlite()) {
            return false;
        }
        $raw = self::env('RATEB_HYBRID_SYNC_ENABLED');
        if ($raw === null || $raw === '') {
            // Default ON for branch appliance once Phase C is installed.
            return true;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    private static int $captureSuppress = 0;

    public static function suppressCapture(bool $suppress): void
    {
        self::$captureSuppress += $suppress ? 1 : -1;
        if (self::$captureSuppress < 0) {
            self::$captureSuppress = 0;
        }
    }

    public static function captureEnabled(): bool
    {
        if (self::$captureSuppress > 0) {
            return false;
        }
        if (!self::enabled()) {
            return false;
        }
        $raw = self::env('RATEB_HYBRID_SYNC_CAPTURE');
        if ($raw === null || $raw === '') {
            return true;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }

    /** HMAC secret for batch signatures (device-local). */
    public static function signingKey(): string
    {
        $key = self::env('RATEB_HYBRID_SYNC_KEY');
        if ($key !== null && $key !== '') {
            return $key;
        }
        // Deterministic device key from branch storage path (not transmitted).
        return hash('sha256', 'rateb-hybrid-sync|' . HybridRuntime::sqlitePath());
    }

    public static function encryptionKey(): string
    {
        return hash('sha256', 'enc|' . self::signingKey(), true);
    }

    public static function cloudMysqlConfigured(): bool
    {
        return defined('RATEB_DB_HOST') && defined('RATEB_DB_NAME') && defined('RATEB_DB_USER');
    }

    /**
     * Sink mode: mysql | mirror
     * mirror = second SQLite file simulating cloud SoT (tests / offline certification).
     */
    public static function sinkMode(): string
    {
        $raw = strtolower((string) (self::env('RATEB_HYBRID_SYNC_SINK') ?? ''));
        if ($raw === 'mysql' || $raw === 'mirror') {
            return $raw;
        }

        return self::cloudMysqlConfigured() ? 'mysql' : 'mirror';
    }

    public static function mirrorPath(): string
    {
        $custom = self::env('RATEB_HYBRID_SYNC_MIRROR');
        if ($custom !== null && $custom !== '') {
            return $custom;
        }

        return HybridRuntime::branchStorageDir() . '/cloud-mirror.sqlite';
    }

    private static function env(string $key): ?string
    {
        $v = $_ENV[$key] ?? getenv($key);
        if ($v === false || $v === null) {
            if (defined($key)) {
                $c = constant($key);

                return is_scalar($c) ? (string) $c : null;
            }

            return null;
        }

        return (string) $v;
    }
}
