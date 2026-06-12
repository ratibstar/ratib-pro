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

        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }

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
            '/app/controllers/Admin/BusinessControllers.php',
            '/app/controllers/Admin/ExtendedControllers.php',
            '/app/controllers/Admin/AccountingControllers.php',
            '/app/controllers/Company/CompanyControllers.php',
            '/app/controllers/Company/ExtendedControllers.php',
            '/app/controllers/Company/AccountingControllers.php',
            '/app/controllers/Company/BusinessControllers.php',
            '/app/controllers/Shared/PasswordResetController.php',
            '/app/controllers/Api/ApiController.php',
            '/app/Core/Router.php',
            '/app/Core/View.php',
            '/app/Core/Response.php',
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

    /** ERP root from this file: app/Core/Bootstrap.php → ../.. */
    public static function erpRootFromBootstrapFile(): string
    {
        $root = realpath(__DIR__ . '/../..');
        if ($root !== false) {
            return str_replace('\\', '/', $root);
        }
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    /**
     * cPanel rewrite can leave callers with /rateb-erp instead of public_html path.
     * Always prefer a root where config/database.php exists.
     */
    public static function resolveRootPath(string $basePath): string
    {
        $basePath = str_replace('\\', '/', rtrim($basePath, '/'));
        $candidates = [
            $basePath,
        ];
        $real = realpath($basePath);
        if ($real !== false) {
            $candidates[] = str_replace('\\', '/', $real);
        }
        $candidates[] = self::erpRootFromBootstrapFile();

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path . '/config/database.php')) {
                return $path;
            }
        }

        return self::erpRootFromBootstrapFile();
    }

    private static function loadConfig(string $basePath): void
    {
        $basePath = self::resolveRootPath($basePath);
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $basePath);
        }
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
