<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class StorageHelper
{
    private static ?string $uploadsRootCache = null;

    /** Writable ERP uploads root (auto-fallback on shared hosting). */
    public static function uploadsRoot(): string
    {
        if (self::$uploadsRootCache !== null) {
            return self::$uploadsRootCache;
        }

        $marker = self::markerFile();
        if (is_readable($marker)) {
            $marked = trim((string) file_get_contents($marker));
            if ($marked !== '' && self::probeWrite($marked)) {
                self::$uploadsRootCache = rtrim(str_replace('\\', '/', $marked), '/');
                return self::$uploadsRootCache;
            }
            @unlink($marker);
        }

        foreach (self::uploadCandidates() as $dir) {
            if (self::probeWrite($dir)) {
                self::writeMarker($dir);
                self::$uploadsRootCache = $dir;
                return $dir;
            }
        }

        self::$uploadsRootCache = rtrim(str_replace('\\', '/', (string) RATEB_STORAGE_PATH), '/') . '/uploads';
        return self::$uploadsRootCache;
    }

    /** Resolve stored DB path (uploads/company_x/...) to absolute file path. */
    public static function resolveFilePath(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false) {
            return '';
        }

        $stripped = preg_replace('#^uploads/#', '', $relative) ?? $relative;
        $candidates = [
            rtrim(str_replace('\\', '/', (string) RATEB_STORAGE_PATH), '/') . '/' . $relative,
            self::uploadsRoot() . '/' . $stripped,
        ];

        $erpParent = dirname((string) RATEB_ROOT);
        $candidates[] = $erpParent . '/uploads/rateb_erp/' . $stripped;
        $candidates[] = dirname($erpParent) . '/rateb_uploads/rateb-erp/' . $stripped;

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return '';
    }

    /** Ensure directory exists and PHP can write a probe file. Returns error message or null. */
    public static function ensureWritableDir(string $dir): ?string
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '') {
            return __('storage_dir_not_writable');
        }

        $root = rtrim(str_replace('\\', '/', self::uploadsRoot()), '/');
        if (strpos($dir, $root) !== 0) {
            if (!self::probeWrite($dir)) {
                return self::writableError($dir);
            }
            return null;
        }

        $relative = ltrim(substr($dir, strlen($root)), '/');
        $chain = [$root];
        if ($relative !== '') {
            $building = $root;
            foreach (explode('/', $relative) as $segment) {
                if ($segment === '') {
                    continue;
                }
                $building .= '/' . $segment;
                $chain[] = $building;
            }
        }

        foreach ($chain as $path) {
            if (!self::probeWrite($path)) {
                return self::writableError($path);
            }
        }

        return null;
    }

    public static function ensureStorageTree(string $basePath): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!defined('RATEB_STORAGE_PATH')) {
            define('RATEB_STORAGE_PATH', rtrim(str_replace('\\', '/', $basePath), '/') . '/storage');
        }
        self::uploadsRoot();
        foreach (['storage/logs', 'storage/backups', 'storage/rate-limit', 'storage/sessions'] as $rel) {
            $path = rtrim(str_replace('\\', '/', $basePath), '/') . '/' . $rel;
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
        }
    }

    /** @return list<string> */
    private static function uploadCandidates(): array
    {
        $erpRoot = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/');
        $parent = dirname($erpRoot);
        $grand = dirname($parent);

        $candidates = [
            rtrim(str_replace('\\', '/', (string) RATEB_STORAGE_PATH), '/') . '/uploads',
            $parent . '/uploads/rateb_erp',
            $parent . '/rateb_uploads/rateb-erp',
            $grand . '/rateb_uploads/rateb-erp',
        ];

        $mainInc = $parent . '/includes/rateb_uploads_base.php';
        if (is_file($mainInc)) {
            require_once $mainInc;
            if (function_exists('rateb_uploads_base_dir')) {
                $candidates[] = rtrim(str_replace('\\', '/', rateb_uploads_base_dir()), '/') . '/rateb_erp';
            }
        }

        $tmp = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $candidates[] = $tmp . '/rateb_erp_uploads_' . substr(md5($erpRoot), 0, 10);

        $seen = [];
        $out = [];
        foreach ($candidates as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $out[] = $path;
        }

        return $out;
    }

    private static function probeWrite(string $dir): bool
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        if ($dir === '') {
            return false;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
        foreach ([0775, 0777] as $mode) {
            @chmod($dir, $mode);
        }
        $probe = $dir . '/.write_probe_' . getmypid();
        if (@file_put_contents($probe, '1') === false) {
            return false;
        }
        @unlink($probe);

        return true;
    }

    private static function markerFile(): string
    {
        return rtrim(str_replace('\\', '/', (string) RATEB_STORAGE_PATH), '/') . '/.rateb_erp_upload_root';
    }

    private static function writeMarker(string $dir): void
    {
        $marker = self::markerFile();
        $markerDir = dirname($marker);
        if (!is_dir($markerDir)) {
            @mkdir($markerDir, 0777, true);
        }
        if (is_dir($markerDir) && is_writable($markerDir)) {
            @file_put_contents($marker, rtrim($dir, '/') . "\n", LOCK_EX);
        }
    }

    private static function writableError(string $path): string
    {
        return __('storage_dir_not_writable') . ' (' . $path . ')';
    }
}
