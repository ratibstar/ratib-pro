<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Config;

final class RuntimeOverrideStore
{
    private static ?string $resolvedWritePath = null;

    /**
     * Resolved path for reads/writes (writable candidate or default).
     */
    public static function path(): string
    {
        return self::resolveWritePath();
    }

    /**
     * Legacy path inside the module (read-only on many production hosts).
     */
    public static function legacyPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
    }

    private static function lockPath(): string
    {
        return dirname(self::resolveWritePath()) . DIRECTORY_SEPARATOR . 'runtime-overrides.lock';
    }

    private static function projectRoot(): string
    {
        $root = dirname(__DIR__, 3);
        $rp = realpath($root);

        return $rp !== false ? $rp : $root;
    }

    private static function defaultPath(): string
    {
        return self::projectRoot() . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
            . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
    }

    /**
     * @return list<string>
     */
    private static function writeCandidatePaths(): array
    {
        $fromEnv = getenv('RATEB_INFRA_RUNTIME_OVERRIDES_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return [trim($fromEnv)];
        }

        $root = self::projectRoot();
        $out = [
            self::defaultPath(),
        ];

        $uploadsHelper = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'rateb_uploads_base.php';
        if (is_file($uploadsHelper)) {
            require_once $uploadsHelper;
            if (function_exists('rateb_uploads_base_dir')) {
                $base = rateb_uploads_base_dir();
                if (is_string($base) && $base !== '') {
                    $out[] = rtrim($base, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
                        . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
                }
            }
            if (function_exists('rateb_uploads_candidate_base_dirs')) {
                foreach (rateb_uploads_candidate_base_dirs(false) as $base) {
                    if (!is_string($base) || $base === '') {
                        continue;
                    }
                    $out[] = rtrim($base, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
                        . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
                }
            }
        }

        $parent = dirname($root);
        if ($parent !== '' && $parent !== '.' && $parent !== $root) {
            $out[] = $parent . DIRECTORY_SEPARATOR . 'rateb_infra' . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
        }

        $unique = [];
        foreach ($out as $p) {
            $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
            if (!in_array($norm, $unique, true)) {
                $unique[] = $norm;
            }
        }

        return $unique;
    }

    private static function resolveWritePath(): string
    {
        if (self::$resolvedWritePath !== null) {
            return self::$resolvedWritePath;
        }

        foreach (self::writeCandidatePaths() as $candidate) {
            if (self::ensureWritableDirectory(dirname($candidate))) {
                self::$resolvedWritePath = $candidate;

                return self::$resolvedWritePath;
            }
        }

        self::$resolvedWritePath = self::defaultPath();

        return self::$resolvedWritePath;
    }

    /**
     * @return list<string>
     */
    private static function readCandidatePaths(): array
    {
        $paths = self::writeCandidatePaths();
        $legacy = self::legacyPath();
        if (!in_array($legacy, $paths, true)) {
            $paths[] = $legacy;
        }

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        foreach (self::readCandidatePaths() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function updateAtomic(callable $mutator): array
    {
        $triedDirs = [];
        $path = null;
        foreach (self::writeCandidatePaths() as $candidate) {
            $dir = dirname($candidate);
            $triedDirs[] = $dir;
            if (self::ensureWritableDirectory($dir)) {
                $path = $candidate;
                self::$resolvedWritePath = $candidate;
                break;
            }
        }
        if ($path === null) {
            throw new \RuntimeException(
                'No writable runtime overrides directory. Tried: ' . implode('; ', array_unique($triedDirs))
            );
        }

        $lockFp = @fopen(self::lockPath(), 'c+');
        if (!is_resource($lockFp)) {
            throw new \RuntimeException('Unable to open runtime config lock file: ' . self::lockPath());
        }

        try {
            if (!@flock($lockFp, LOCK_EX)) {
                throw new \RuntimeException('Unable to acquire runtime config lock');
            }

            $old = self::read();
            $new = $mutator($old);
            if (!is_array($new)) {
                throw new \RuntimeException('Runtime override mutator returned invalid payload');
            }

            $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
            $json = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                throw new \RuntimeException('Unable to encode runtime overrides json');
            }
            $json .= PHP_EOL;

            $written = @file_put_contents($tmp, $json, LOCK_EX);
            if ($written === false) {
                throw new \RuntimeException('Unable to write runtime overrides temporary file: ' . $tmp);
            }

            @chmod($tmp, 0660);

            if (!@rename($tmp, $path)) {
                @unlink($path);
                if (!@rename($tmp, $path)) {
                    @unlink($tmp);
                    throw new \RuntimeException('Unable to atomically replace runtime overrides file: ' . $path);
                }
            }

            ModuleConfig::clearRuntimeOverridesCache();

            return ['old' => $old, 'new' => $new];
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
    }

    private static function ensureWritableDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            return false;
        }
        if (@is_writable($dir)) {
            return true;
        }
        @chmod($dir, 0775);
        if (@is_writable($dir)) {
            return true;
        }
        @chmod($dir, 0777);

        return @is_writable($dir);
    }
}
