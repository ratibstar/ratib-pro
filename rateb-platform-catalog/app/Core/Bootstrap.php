<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class Bootstrap
{
    private static bool $booted = false;

    public static function init(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_CATALOG_ROOT')) {
            define('RATEB_CATALOG_ROOT', $basePath);
        }

        if (PHP_VERSION_ID < 80100) {
            http_response_code(500);
            exit('RATEB Platform Catalog requires PHP 8.1 or newer.');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        date_default_timezone_set('Asia/Riyadh');

        self::registerAutoloader($basePath);
        self::loadConfig($basePath);
        self::ensureStorage($basePath);
    }

    public static function initMinimal(string $basePath): void
    {
        if (!defined('RATEB_CATALOG_NO_SESSION')) {
            define('RATEB_CATALOG_NO_SESSION', true);
        }

        self::init($basePath);
    }

    private static function resolveRootPath(string $basePath): string
    {
        $resolved = realpath($basePath);

        return $resolved !== false ? $resolved : $basePath;
    }

    private static function registerAutoloader(string $basePath): void
    {
        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = 'Rateb\\PlatformCatalog\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', '/', $relative) . '.php';
            $candidates = [
                $basePath . '/app/' . $relativePath,
            ];

            foreach ($candidates as $file) {
                if (!is_file($file)) {
                    $dir = dirname($file);
                    $name = basename($file);
                    if (is_dir($dir)) {
                        $lower = $dir . '/' . strtolower($name);
                        if (is_file($lower)) {
                            $file = $lower;
                        }
                    }
                }

                if (is_file($file)) {
                    require_once $file;

                    return;
                }
            }
        });
    }

    private static function loadConfig(string $basePath): void
    {
        foreach ([
            'app.php',
            'release.php',
            'database.php',
            'storage.php',
            'upload.php',
            's3.php',
            'languages.php',
            'gateway.php',
        ] as $configFile) {
            $path = $basePath . '/config/' . $configFile;
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    private static function ensureStorage(string $basePath): void
    {
        $storageRoot = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
            ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
            : $basePath . '/storage';

        foreach ([
            'catalog/products',
            'catalog/categories',
            'catalog/brands',
            'logs',
            'cache',
            'queue',
            'backups',
        ] as $dir) {
            $full = $storageRoot . '/' . $dir;
            if (!is_dir($full)) {
                @mkdir($full, 0755, true);
            }
        }
    }
}
