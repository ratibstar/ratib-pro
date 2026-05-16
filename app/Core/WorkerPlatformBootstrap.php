<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Bootstrap worker-platform HTTP endpoints (workflow onboarding, worker-platform.php siblings).
 * Resolves project root on cPanel whether docroot is repo root or public/.
 */
final class WorkerPlatformBootstrap
{
    public static function projectRootFrom(string $entryDir): string
    {
        $dir = realpath($entryDir) ?: $entryDir;
        for ($i = 0; $i < 12; $i++) {
            $marker = $dir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
            if (is_file($marker)) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new RuntimeException(
            'Could not locate project root (app/Core/Autoloader.php) from ' . $entryDir
        );
    }

    /**
     * Register autoloader, helpers, and Ratib Pro session (includes/config.php) when present.
     */
    public static function init(string $entryDir): string
    {
        $root = self::projectRootFrom($entryDir);

        $autoloaderFile = $root . '/app/Core/Autoloader.php';
        if (!is_file($autoloaderFile)) {
            throw new RuntimeException('Missing app/Core/Autoloader.php under ' . $root);
        }
        require_once $autoloaderFile;
        \App\Core\Autoloader::register($root . DIRECTORY_SEPARATOR . 'app');

        if (is_file($root . '/app/Core/helpers.php')) {
            require_once $root . '/app/Core/helpers.php';
        }
        if (is_file($root . '/app/Core/ErrorTracker.php')) {
            require_once $root . '/app/Core/ErrorTracker.php';
        }

        $legacyConfig = $root . '/includes/config.php';
        if (is_file($legacyConfig)) {
            require_once $legacyConfig;
        } elseif (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $root;
    }

    /**
     * Same tenant database as Ratib Pro pages/API (agency DB when ?agency_id= / control SSO).
     *
     * @return array{host:string,port:int,database:string,username:string,password:string,charset:string}
     */
    public static function ratibDatabaseConfig(): array
    {
        $agencyDb = $GLOBALS['agency_db'] ?? null;
        if (is_array($agencyDb) && !empty($agencyDb['db'])) {
            return [
                'host' => (string) ($agencyDb['host'] ?? 'localhost'),
                'port' => (int) ($agencyDb['port'] ?? 3306),
                'database' => (string) $agencyDb['db'],
                'username' => (string) ($agencyDb['user'] ?? (defined('DB_USER') ? DB_USER : '')),
                'password' => (string) ($agencyDb['pass'] ?? (defined('DB_PASS') ? DB_PASS : '')),
                'charset' => 'utf8mb4',
            ];
        }

        return [
            'host' => defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1',
            'port' => defined('DB_PORT') ? (int) DB_PORT : 3306,
            'database' => defined('RATIB_PRO_DB_NAME') && (string) RATIB_PRO_DB_NAME !== ''
                ? (string) RATIB_PRO_DB_NAME
                : (defined('DB_NAME') ? (string) DB_NAME : ''),
            'username' => defined('DB_USER') ? (string) DB_USER : '',
            'password' => defined('DB_PASS') ? (string) DB_PASS : '',
            'charset' => 'utf8mb4',
        ];
    }
}
