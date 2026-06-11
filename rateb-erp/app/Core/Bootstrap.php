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
            '/app/controllers/CrudController.php',
            '/app/helpers/LineItems.php',
            '/app/services/AuditService.php',
            '/app/controllers/Admin/AdminControllers.php',
            '/app/controllers/Admin/ExtendedControllers.php',
            '/app/controllers/Admin/AccountingControllers.php',
            '/app/controllers/Company/CompanyControllers.php',
            '/app/controllers/Company/ExtendedControllers.php',
            '/app/controllers/Company/AccountingControllers.php',
            '/app/controllers/Company/BusinessControllers.php',
            '/app/controllers/Shared/PasswordResetController.php',
            '/app/controllers/Api/ApiController.php',
            '/app/Core/Middleware/Middleware.php',
            '/app/services/MigrationService.php',
            '/app/services/PlanLimitService.php',
            '/app/services/AccountingService.php',
            '/app/services/BillingService.php',
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

            $relative = substr($class, strlen($prefix));
            $path = self::resolveAutoloadPath($basePath, $relative);
            if ($path !== null) {
                require_once $path;
            }
        });
    }

    /** Linux cPanel is case-sensitive: namespace Controllers vs folder controllers. */
    private static function resolveAutoloadPath(string $basePath, string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $exact = $basePath . '/app/' . $relative . '.php';
        if (is_file($exact)) {
            return $exact;
        }

        $parts = explode('/', $relative);
        $classFile = array_pop($parts) . '.php';
        $dir = $basePath . '/app';

        foreach ($parts as $segment) {
            $next = self::matchPathSegment($dir, $segment, true);
            if ($next === null) {
                return null;
            }
            $dir = $next;
        }

        return self::matchPathSegment($dir, $classFile, false);
    }

    private static function matchPathSegment(string $parent, string $name, bool $directory): ?string
    {
        if (!is_dir($parent)) {
            return null;
        }

        $target = strtolower($name);
        foreach (scandir($parent) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) !== $target) {
                continue;
            }
            $full = $parent . '/' . $entry;
            if ($directory && is_dir($full)) {
                return $full;
            }
            if (!$directory && is_file($full)) {
                return $full;
            }
        }

        return null;
    }

    private static function loadConfig(string $basePath): void
    {
        require_once $basePath . '/config/app.php';
        require_once $basePath . '/config/database.php';
    }

    private static function ensureStorage(string $basePath): void
    {
        foreach (['storage/logs', 'storage/uploads', 'storage/backups', 'storage/rate-limit'] as $dir) {
            $full = $basePath . '/' . $dir;
            if (!is_dir($full)) {
                @mkdir($full, 0755, true);
            }
        }
    }
}
