<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Enterprise Hybrid Runtime — Phase A Core Seam.
 *
 * Selects the operational database driver without altering Controllers,
 * Services, Models, Routes, or Views. Default is cloud MySQL (identical
 * to pre-hybrid behaviour). Branch mode uses local SQLite.
 */
final class HybridRuntime
{
    public const MODE_CLOUD = 'cloud';
    public const MODE_BRANCH = 'branch';

    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_SQLITE = 'sqlite';

    private static ?string $modeMemo = null;
    private static ?string $sqlitePathMemo = null;
    private static ?string $driverMemo = null;

    /** Clear memoization (tests / CLI mode switches). */
    public static function reset(): void
    {
        self::$modeMemo = null;
        self::$sqlitePathMemo = null;
        self::$driverMemo = null;
    }

    /**
     * Runtime mode: cloud (MySQL) or branch (SQLite).
     * Detection order: env RATEB_RUNTIME → constant RATEB_RUNTIME → marker file.
     */
    public static function mode(): string
    {
        if (self::$modeMemo !== null) {
            return self::$modeMemo;
        }

        $raw = self::readRuntimeHint();
        if ($raw === self::MODE_BRANCH || $raw === 'sqlite' || $raw === 'local') {
            return self::$modeMemo = self::MODE_BRANCH;
        }

        return self::$modeMemo = self::MODE_CLOUD;
    }

    public static function isBranchMode(): bool
    {
        return self::mode() === self::MODE_BRANCH;
    }

    public static function isCloudMode(): bool
    {
        return self::mode() === self::MODE_CLOUD;
    }

    /**
     * Whether Database::connection() must open SQLite.
     * Requires branch mode AND PDO SQLite availability.
     */
    public static function shouldUseSqlite(): bool
    {
        if (!self::isBranchMode()) {
            return false;
        }

        return self::sqliteExtensionAvailable();
    }

    public static function sqliteExtensionAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    public static function driver(): string
    {
        if (self::$driverMemo !== null) {
            return self::$driverMemo;
        }

        return self::$driverMemo = self::shouldUseSqlite()
            ? self::DRIVER_SQLITE
            : self::DRIVER_MYSQL;
    }

    public static function isSqliteDriver(): bool
    {
        return self::driver() === self::DRIVER_SQLITE;
    }

    /**
     * Absolute path to the branch SQLite file.
     * Override: RATEB_SQLITE_PATH or constant RATEB_SQLITE_PATH.
     */
    public static function sqlitePath(): string
    {
        if (self::$sqlitePathMemo !== null) {
            return self::$sqlitePathMemo;
        }

        $fromEnv = getenv('RATEB_SQLITE_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::$sqlitePathMemo = self::normalizePath(trim($fromEnv));
        }
        if (defined('RATEB_SQLITE_PATH') && trim((string) RATEB_SQLITE_PATH) !== '') {
            return self::$sqlitePathMemo = self::normalizePath(trim((string) RATEB_SQLITE_PATH));
        }

        $root = self::erpRoot();
        $default = $root . '/storage/branch/rateb-branch.sqlite';

        return self::$sqlitePathMemo = self::normalizePath($default);
    }

    public static function branchStorageDir(): string
    {
        return self::normalizePath(dirname(self::sqlitePath()));
    }

    public static function runtimeMarkerPath(): string
    {
        return self::normalizePath(self::erpRoot() . '/storage/branch/runtime.mode');
    }

    /**
     * Ensure branch storage directory exists (no schema yet).
     */
    public static function ensureBranchStorage(): string
    {
        $dir = self::branchStorageDir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create branch storage: ' . $dir);
            }
        }

        return $dir;
    }

    /** @return array{mode:string,driver:string,sqlite_path:string,sqlite_extension:bool,branch_mode:bool} */
    public static function snapshot(): array
    {
        return [
            'mode' => self::mode(),
            'driver' => self::driver(),
            'sqlite_path' => self::sqlitePath(),
            'sqlite_extension' => self::sqliteExtensionAvailable(),
            'branch_mode' => self::isBranchMode(),
        ];
    }

    private static function readRuntimeHint(): string
    {
        $fromEnv = getenv('RATEB_RUNTIME');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return strtolower(trim($fromEnv));
        }
        if (isset($_ENV['RATEB_RUNTIME']) && is_string($_ENV['RATEB_RUNTIME']) && trim($_ENV['RATEB_RUNTIME']) !== '') {
            return strtolower(trim($_ENV['RATEB_RUNTIME']));
        }
        if (defined('RATEB_RUNTIME') && trim((string) RATEB_RUNTIME) !== '') {
            return strtolower(trim((string) RATEB_RUNTIME));
        }

        $marker = self::runtimeMarkerPath();
        if (is_file($marker)) {
            $contents = @file_get_contents($marker);
            if (is_string($contents) && trim($contents) !== '') {
                return strtolower(trim($contents));
            }
        }

        return self::MODE_CLOUD;
    }

    private static function erpRoot(): string
    {
        if (defined('RATEB_ROOT') && (string) RATEB_ROOT !== '') {
            return self::normalizePath((string) RATEB_ROOT);
        }

        return self::normalizePath(dirname(__DIR__, 2));
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
