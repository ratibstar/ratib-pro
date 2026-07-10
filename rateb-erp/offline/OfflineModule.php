<?php

declare(strict_types=1);

namespace Rateb\App\Offline;

/**
 * Enterprise Offline module bootstrap — isolated under offline/.
 * Additive only; does not alter existing ERP modules.
 */
final class OfflineModule
{
    private const ROOT_REL = '/offline';

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
        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $root), '/') . self::ROOT_REL;
    }

    public static function serverPath(): string
    {
        return self::rootPath() . '/server';
    }

    /** @return array<string, mixed> */
    public static function featureFlagsConfig(): array
    {
        $file = self::rootPath() . '/config/feature-flags.php';
        return is_file($file) ? require $file : [];
    }

    /** @return array<string, mixed> */
    public static function syncPolicy(): array
    {
        $file = self::rootPath() . '/config/sync-policy.php';
        return is_file($file) ? require $file : [];
    }

    private static function registerAutoload(): void
    {
        $server = self::serverPath();
        spl_autoload_register(static function (string $class) use ($server): void {
            $prefix = 'Rateb\\App\\Offline\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $path = $server . '/' . $relative;
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
