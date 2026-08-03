<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu;

/**
 * Guest-facing digital menu (QR browse) — F&B Layer 2.
 */
final class GuestMenuModule
{
    private const ROOT_REL = '/modules/guest-menu';

    private static bool $booted = false;

    /** @var array<string, array<string, string>> */
    private static array $langCache = [];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $entityPermissions = null;

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

    public static function viewsPath(): string
    {
        return self::rootPath() . '/views';
    }

    /** @return array<string, array<string, mixed>> */
    public static function entityPermissions(): array
    {
        if (self::$entityPermissions === null) {
            $file = self::rootPath() . '/config/entity-permissions.php';
            self::$entityPermissions = is_file($file) ? require $file : [];
        }

        return self::$entityPermissions;
    }

    public static function translate(string $key, array $replace = []): ?string
    {
        if (!function_exists('rateb_locale')) {
            return null;
        }
        $locale = rateb_locale();
        if (!isset(self::$langCache[$locale])) {
            $file = self::rootPath() . '/config/lang/' . $locale . '.php';
            self::$langCache[$locale] = is_file($file) ? require $file : [];
        }
        if (!array_key_exists($key, self::$langCache[$locale])) {
            return null;
        }
        $text = (string) self::$langCache[$locale][$key];
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }

        return $text;
    }

    private static function registerAutoload(): void
    {
        $root = self::rootPath();
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Rateb\\App\\GuestMenu\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $path = $root . '/app/' . $relative;
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
