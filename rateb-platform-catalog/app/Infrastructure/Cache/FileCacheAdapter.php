<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Cache;

final class FileCacheAdapter implements CacheAdapterInterface
{
    public function __construct(
        private readonly string $cachePath,
        private readonly string $prefix = 'cat:'
    ) {
        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0775, true) && !is_dir($this->cachePath)) {
            throw new \RuntimeException('Unable to create cache directory');
        }
    }

    public function get(string $key): ?string
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['expires_at'], $decoded['value'])) {
            return null;
        }
        if ((int) $decoded['expires_at'] < time()) {
            @unlink($path);

            return null;
        }

        return is_string($decoded['value']) ? $decoded['value'] : json_encode($decoded['value'], JSON_UNESCAPED_UNICODE);
    }

    public function set(string $key, ?array $value, int $ttlSeconds): void
    {
        $path = $this->pathFor($key);
        $payload = json_encode([
            'expires_at' => time() + max(1, $ttlSeconds),
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new \RuntimeException('Unable to encode cache payload');
        }
        if (file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write cache file');
        }
    }

    public function delete(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function healthCheck(): bool
    {
        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0775, true) && !is_dir($this->cachePath)) {
            return false;
        }

        try {
            $key = 'health_probe_' . bin2hex(random_bytes(4));
            $this->set($key, ['ok' => true], 10);
            $value = $this->get($key);
            $this->delete($key);

            return $value !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pathFor(string $key): string
    {
        return $this->cachePath . '/' . hash('sha256', $this->prefix . $key) . '.json';
    }
}
