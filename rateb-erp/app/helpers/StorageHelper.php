<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class StorageHelper
{
    /** Ensure directory exists and PHP can write a probe file. Returns error message or null. */
    public static function ensureWritableDir(string $dir): ?string
    {
        $dir = str_replace('\\', '/', $dir);
        $root = rtrim(str_replace('\\', '/', (string) RATEB_STORAGE_PATH), '/');
        if ($dir === '' || strpos($dir, $root) !== 0) {
            return __('storage_dir_not_writable');
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
            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                return self::writableError($path);
            }
            if (!self::makeWritable($path)) {
                return self::writableError($path);
            }
        }

        $probe = $dir . '/.write_probe_' . getmypid();
        if (@file_put_contents($probe, '1') === false) {
            return self::writableError($dir);
        }
        @unlink($probe);

        return null;
    }

    public static function ensureStorageTree(string $basePath): void
    {
        foreach (['storage', 'storage/uploads', 'storage/logs', 'storage/backups', 'storage/rate-limit'] as $rel) {
            self::ensureWritableDir($basePath . '/' . $rel);
        }
    }

    private static function makeWritable(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }
        if (is_writable($path)) {
            return true;
        }
        foreach ([0775, 0777] as $mode) {
            @chmod($path, $mode);
            if (is_writable($path)) {
                return true;
            }
        }
        return is_writable($path);
    }

    private static function writableError(string $path): string
    {
        return __('storage_dir_not_writable') . ' (' . $path . ')';
    }
}
