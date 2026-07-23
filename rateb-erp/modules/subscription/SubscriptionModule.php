<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Subscription Engine module bootstrap — isolated under modules/subscription/.
 *
 * Phase 2:
 * - Registers module-local PSR-4 autoload for Rateb\App\Subscription\*
 * - Loads public subscription() helper
 * - Does NOT register routes, cron, UI, redirects, or access blocking
 */
final class SubscriptionModule
{
    private const ROOT_REL = '/modules/subscription';

    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::registerAutoload();
        $helpers = self::rootPath() . '/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    public static function rootPath(): string
    {
        return RATEB_ROOT . self::ROOT_REL;
    }

    private static function registerAutoload(): void
    {
        $root = self::rootPath();
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Rateb\\App\\Subscription\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $path = $root . '/' . $relative;
            // Phase 9: Admin\* maps to lowercase admin/ on disk.
            if (!is_file($path) && str_starts_with($relative, 'Admin/')) {
                $path = $root . '/admin/' . substr($relative, strlen('Admin/'));
            }
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
