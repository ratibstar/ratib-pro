<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase B.2 / B.2.1 — local-device advisory locks for MySQL GET_LOCK / RELEASE_LOCK.
 *
 * Uses flock() under storage/branch/locks/. Process-local held handles track ownership.
 * Cloud MySQL never loads this class (SQLite connection path only).
 *
 * Behavioral differences vs MySQL GET_LOCK:
 * - Scope is the local branch device filesystem, not the MySQL server session map.
 * - Cross-process on the same machine works via flock (multiple PHP workers / browser sessions).
 * - Cross-device does not apply (single-branch offline appliance).
 * - Crash / process exit: OS releases flock when FDs close; shutdown handler also RELEASE_LOCKs.
 *
 * Certification (B.2.1): sufficient for single-branch offline appliance concurrency.
 */
final class SqliteAdvisoryLock
{
    /** @var array<string, resource> */
    private static array $held = [];

    private static bool $shutdownRegistered = false;

    public static function get(string $name, int $timeoutSeconds = 0): int
    {
        self::ensureShutdownHandler();
        $key = self::normalize($name);
        if ($key === '') {
            return 0;
        }
        if (isset(self::$held[$key])) {
            return 1; // re-entrant for same PHP process
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
                if (microtime(true) >= $deadline) {
                    break;
                }
                usleep(20_000);
                continue;
            }
            if (@flock($fh, LOCK_EX | LOCK_NB)) {
                // Best-effort ownership marker (diagnostics only; flock is the authority).
                @ftruncate($fh, 0);
                @fwrite($fh, (string) getmypid() . "\n" . (string) time() . "\n");
                @fflush($fh);
                self::$held[$key] = $fh;

                return 1;
            }
            fclose($fh);
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(20_000);
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

    /** Release every lock held by this process (shutdown / crash hygiene). */
    public static function releaseAll(): void
    {
        foreach (array_keys(self::$held) as $key) {
            self::release($key);
        }
    }

    /** @return list<string> */
    public static function heldNames(): array
    {
        return array_keys(self::$held);
    }

    private static function ensureShutdownHandler(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            self::releaseAll();
        });
    }

    private static function normalize(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?? '';

        return substr($safe, 0, 180);
    }
}
