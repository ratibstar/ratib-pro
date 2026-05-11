<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Config;

final class RuntimeOverrideStore
{
    public static function path(): string
    {
        return dirname(__DIR__) . '/Config/runtime-overrides.json';
    }

    private static function lockPath(): string
    {
        return dirname(__DIR__) . '/Config/runtime-overrides.lock';
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public static function updateAtomic(callable $mutator): array
    {
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create runtime config directory');
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

            @chmod($tmp, 0640);

            if (!@rename($tmp, $path)) {
                @unlink($path);
                if (!@rename($tmp, $path)) {
                    @unlink($tmp);
                    throw new \RuntimeException('Unable to atomically replace runtime overrides file');
                }
            }

            return ['old' => $old, 'new' => $new];
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
    }
}

