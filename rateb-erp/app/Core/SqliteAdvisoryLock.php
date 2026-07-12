<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase B.2 — local-device advisory locks for MySQL GET_LOCK / RELEASE_LOCK.
 *
 * Uses flock() under storage/branch/locks/. Process-local held handles track ownership.
 * Cloud MySQL never loads this class (SQLite connection path only).
 *
 * Behavioral differences vs MySQL GET_LOCK:
 * - Scope is the local branch device filesystem, not the MySQL server session map.
 * - Cross-process on the same machine works via flock; cross-device does not (branch offline).
 * - Connection close does not auto-release (caller must RELEASE_LOCK; WarehouseService does).
 */
final class SqliteAdvisoryLock
{
    /** @var array<string, resource> */
    private static array $held = [];

    public static function get(string $name, int $timeoutSeconds = 0): int
    {
        $key = self::normalize($name);
        if ($key === '') {
            return 0;
        }
        if (isset(self::$held[$key])) {
            return 1; // re-entrant for same PHP process (MySQL session re-get returns 1)
        }

        $dir = HybridRuntime::branchStorageDir() . '/locks';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return 0;
        }
        $path = $dir . '/' . $key . '.lock';
        $deadline = microtime(true) + max(0, $timeoutSeconds);
        do {
            $fh = @fopen($path, 'c+');
            if ($fh === false) {
                usleep(50_000);
                continue;
            }
            if (@flock($fh, LOCK_EX | LOCK_NB)) {
                self::$held[$key] = $fh;

                return 1;
            }
            fclose($fh);
            usleep(50_000);
        } while (microtime(true) < $deadline);

        return 0;
    }

    public static function release(string $name): int
    {
        $key = self::normalize($name);
        if ($key === '' || !isset(self::$held[$key])) {
            return 0;
        }
        $fh = self::$held[$key];
        unset(self::$held[$key]);
        @flock($fh, LOCK_UN);
        @fclose($fh);

        return 1;
    }

    private static function normalize(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?? '';

        return substr($safe, 0, 180);
    }
}
