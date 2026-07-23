<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Subscription Engine module bootstrap — isolated under modules/subscription/.
 *
 * Phase 1 foundation only:
 * - Registers module-local PSR-4 autoload for Rateb\App\Subscription\*
 * - Does NOT register routes, middleware, cron, UI, or login hooks
 * - Must NOT be required from public/index.php until a later approved phase
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
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
