<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Branch appliance storage layout (Core only).
 */
final class BranchAppliancePaths
{
    public static function root(): string
    {
        return HybridRuntime::branchStorageDir();
    }

    public static function ensureLayout(): array
    {
        HybridRuntime::ensureBranchStorage();
        $dirs = [
            'logs',
            'backups',
            'backups/meta',
            'registration',
            'identity',
            'health',
            'updates',
            'updates/rollback',
            'recovery',
            'diagnostics',
            'package',
        ];
        $created = [];
        foreach ($dirs as $rel) {
            $path = self::root() . '/' . $rel;
            if (!is_dir($path)) {
                @mkdir($path, 0770, true);
            }
            $created[$rel] = is_dir($path) && is_writable($path);
        }

        return $created;
    }

    public static function serveEnv(): string
    {
        return self::root() . '/serve.env';
    }

    public static function identityDir(): string
    {
        return self::root() . '/identity';
    }

    public static function backupsDir(): string
    {
        return self::root() . '/backups';
    }

    public static function versionFile(): string
    {
        $erp = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 2);

        return str_replace('\\', '/', $erp) . '/VERSION';
    }

    public static function readVersion(): string
    {
        $f = self::versionFile();
        if (is_file($f)) {
            return trim((string) file_get_contents($f));
        }
        $build = dirname(self::versionFile()) . '/public/ratib-build.txt';
        if (is_file($build)) {
            return trim((string) file_get_contents($build));
        }

        return '0.0.0-unknown';
    }
}
