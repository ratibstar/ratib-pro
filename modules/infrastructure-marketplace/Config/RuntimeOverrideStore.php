<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Config;

final class RuntimeOverrideStore
{
    /**
     * Writable runtime config (module tree is often read-only on cPanel after deploy).
     */
    public static function path(): string
    {
        $fromEnv = getenv('RATIB_INFRA_RUNTIME_OVERRIDES_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return self::projectRoot() . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
            . DIRECTORY_SEPARATOR . 'runtime-overrides.json';
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
        return dirname(self::path()) . DIRECTORY_SEPARATOR . 'runtime-overrides.lock';
    }

    private static function projectRoot(): string
    {
        $root = dirname(__DIR__, 3);
        $rp = realpath($root);

        return $rp !== false ? $rp : $root;
    }

    /**
     * @return list<string>
     */
    private static function readCandidatePaths(): array
    {
        $primary = self::path();
        $legacy = self::legacyPath();
        if ($primary === $legacy) {
            return [$primary];
        }

        return [$primary, $legacy];
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
        $path = self::path();
        $dir = dirname($path);
        if (!self::ensureWritableDirectory($dir)) {
            throw new \RuntimeException('Unable to create runtime config directory: ' . $dir);
        }

        $lockFp = @fopen(self::lockPath(), 'c+');
        if (!is_resource($lockFp)) {
            throw new \RuntimeException('Unable to open runtime config lock file');
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
                throw new \RuntimeException('Unable to write runtime overrides temporary file');
            }

            @chmod($tmp, 0660);

            if (!@rename($tmp, $path)) {
                @unlink($path);
                if (!@rename($tmp, $path)) {
                    @unlink($tmp);
                    throw new \RuntimeException('Unable to atomically replace runtime overrides file');
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

        return @is_writable($dir);
    }
}
