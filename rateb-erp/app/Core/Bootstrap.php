<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class Bootstrap
{
    private static bool $booted = false;

    public static function init(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        if (PHP_VERSION_ID < 70400) {
            http_response_code(500);
            exit('RATEB ERP requires PHP 7.4 or newer.');
        }

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        date_default_timezone_set('Asia/Riyadh');

        self::registerAutoloader($basePath);
        $requestHelper = $basePath . '/app/helpers/Request.php';
        if (is_file($requestHelper)) {
            require_once $requestHelper;
        }
        $entities = $basePath . '/app/models/Entities.php';
        if (is_file($entities)) {
            require_once $entities;
        }
        foreach ([
            '/app/controllers/Admin/AdminControllers.php',
            '/app/controllers/Company/CompanyControllers.php',
            '/app/controllers/Api/ApiController.php',
            '/app/Core/Middleware/Middleware.php',
            '/app/services/MigrationService.php',
        ] as $bundle) {
            $f = $basePath . $bundle;
            if (is_file($f)) {
                require_once $f;
            }
        }
        self::loadConfig($basePath);
        self::ensureStorage($basePath);
        SessionManager::start();
    }

    private static function registerAutoloader(string $basePath): void
    {
        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = 'Rateb\\App\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
            $paths = [
                $basePath . '/app/' . $relative . '.php',
                $basePath . '/app/' . strtolower($relative) . '.php',
            ];

            foreach ($paths as $path) {
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });
    }

    private static function loadConfig(string $basePath): void
    {
        require_once $basePath . '/config/app.php';
        require_once $basePath . '/config/database.php';
    }

    private static function ensureStorage(string $basePath): void
    {
        foreach (['storage/logs', 'storage/uploads'] as $dir) {
            $full = $basePath . '/' . $dir;
            if (!is_dir($full)) {
                @mkdir($full, 0755, true);
            }
        }
    }
}
