<?php

declare(strict_types=1);

namespace Rateb\App\Offline;

/**
 * Enterprise Offline module bootstrap — isolated under offline/.
 * Additive only; does not alter existing ERP modules.
 * Phase D.1: soft-loaded from public/index.php when file is present on host.
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

    /** @var array<string, mixed>|null */
    private static ?array $opsAllowlistMemo = null;

    /** Clear memoized allowlist (tests). */
    public static function resetOpsAllowlistMemo(): void
    {
        self::$opsAllowlistMemo = null;
    }

    /**
     * Prefer prebuilt JSON (routes already resolved). Never re-resolve 140+
     * rateb_app_route() calls on every HTML request — that made admin feel multi-second slow.
     *
     * @return array{
     *   paths?: list<string>,
     *   routes?: array<string, string>,
     *   form_hooks?: list<array<string, string>>
     * }
     */
    public static function opsPageAllowlist(): array
    {
        if (self::$opsAllowlistMemo !== null) {
            return self::$opsAllowlistMemo;
        }

        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__);
        $jsonPath = rtrim(str_replace('\\', '/', $root), '/') . '/public/assets/offline/ops-page-allowlist.json';
        if (is_file($jsonPath)) {
            $raw = @file_get_contents($jsonPath);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['paths']) && is_array($decoded['paths'])) {
                $routes = [];
                if (isset($decoded['routes']) && is_array($decoded['routes'])) {
                    foreach ($decoded['routes'] as $k => $v) {
                        $routes[(string) $k] = trim((string) $v, "/ \t\n\r");
                    }
                }
                $paths = array_values(array_filter(array_map(
                    static fn ($p): string => trim((string) $p, "/ \t\n\r"),
                    $decoded['paths']
                ), static fn (string $p): bool => $p !== ''));

                return self::$opsAllowlistMemo = [
                    'version' => (int) ($decoded['version'] ?? 2),
                    'paths' => $paths,
                    'routes' => $routes,
                    'form_hooks' => is_array($decoded['form_hooks'] ?? null) ? $decoded['form_hooks'] : [],
                ];
            }
        }

        $file = self::rootPath() . '/config/ops-page-allowlist.php';
        $cfg = is_file($file) ? require $file : [];
        if (!is_array($cfg)) {
            return self::$opsAllowlistMemo = [];
        }

        $paths = array_values(array_filter(array_map(
            static fn ($p): string => trim((string) $p, "/ \t\n\r"),
            $cfg['paths'] ?? []
        ), static fn (string $p): bool => $p !== ''));

        $routes = [];
        if (function_exists('rateb_app_route')) {
            foreach ($paths as $logical) {
                $canonical = trim((string) rateb_app_route($logical), "/ \t\n\r");
                if ($canonical !== '') {
                    $routes[$logical] = $canonical;
                }
            }
        }

        $cfg['paths'] = $paths;
        $cfg['routes'] = $routes;

        return self::$opsAllowlistMemo = $cfg;
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
