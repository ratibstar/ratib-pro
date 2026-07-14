<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Route module loader — Phase AA.1 identity façade.
 * loadAll() requires every route file in today's index.php order. No selection yet.
 */
final class RouteModuleLoader
{
    /** @var list<string> */
    private static array $lastLoadedIds = [];

    /** @var list<string> */
    private static array $lastLoadedFiles = [];

    /**
     * Require every module in routes/manifest.php (same order as legacy index.php).
     * $router must be named such that route files see local $router (legacy scope).
     *
     * @return list<string> Loaded module ids
     */
    public static function loadAll(Router $router): array
    {
        self::$lastLoadedIds = [];
        self::$lastLoadedFiles = [];

        if (!defined('RATEB_ROOT')) {
            throw new \RuntimeException('RATEB_ROOT must be defined before RouteModuleLoader::loadAll()');
        }

        $manifestPath = RATEB_ROOT . '/routes/manifest.php';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Route manifest missing: ' . $manifestPath);
        }

        /** @var list<array{id:string,file:string,optional?:bool}> $modules */
        $modules = require $manifestPath;
        if (!is_array($modules)) {
            throw new \RuntimeException('Route manifest must return a list of modules');
        }

        // Route files register into local $router (same as when required from index.php).
        foreach ($modules as $module) {
            $id = (string) ($module['id'] ?? '');
            $rel = (string) ($module['file'] ?? '');
            $optional = !empty($module['optional']);
            if ($id === '' || $rel === '') {
                continue;
            }
            $path = RATEB_ROOT . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            if (!is_file($path)) {
                if ($optional) {
                    continue;
                }
                throw new \RuntimeException('Required route module file missing: ' . $path);
            }
            require $path;
            self::$lastLoadedIds[] = $id;
            self::$lastLoadedFiles[] = $rel;
        }

        return self::$lastLoadedIds;
    }

    /** @return list<string> */
    public static function lastLoadedIds(): array
    {
        return self::$lastLoadedIds;
    }

    /** @return list<string> */
    public static function lastLoadedFiles(): array
    {
        return self::$lastLoadedFiles;
    }
}
