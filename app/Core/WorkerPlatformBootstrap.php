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

        require_once $root . '/app/Core/Autoloader.php';
        Autoloader::register($root . DIRECTORY_SEPARATOR . 'app');

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
}
