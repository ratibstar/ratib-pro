<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class LocalStorageAdapter implements StorageAdapterInterface
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $publicBaseUrl = '',
        private readonly bool $signedUrlsEnabled = false,
        private readonly ?SignedUrlGenerator $signedUrlGenerator = null
    ) {
    }

    public function put(string $relativePath, mixed $content, array $meta = []): StoredObject
    {
        $normalized = $this->normalizeKey($relativePath);
        $absolute = $this->absolutePath($normalized);
        $dir = dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create storage directory');
        }

        if (is_resource($content)) {
            $dest = fopen($absolute, 'wb');
            if ($dest === false) {
                throw new \RuntimeException('Unable to open storage file for writing');
            }
            stream_copy_to_stream($content, $dest);
            fclose($dest);
        } else {
            if (file_put_contents($absolute, (string) $content) === false) {
                throw new \RuntimeException('Unable to write storage file');
            }
        }

        $mimeType = (string) ($meta['mime_type'] ?? 'application/octet-stream');
        $size = (int) (filesize($absolute) ?: 0);

        return new StoredObject($normalized, $size, $mimeType, $meta);
    }

    public function get(string $relativePath)
    {
        $absolute = $this->absolutePath($this->normalizeKey($relativePath));
        if (!is_file($absolute)) {
            throw new \RuntimeException('Storage object not found', 404);
        }

        $handle = fopen($absolute, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to read storage object');
        }

        return $handle;
    }

    public function delete(string $relativePath): void
    {
        $absolute = $this->absolutePath($this->normalizeKey($relativePath));
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($this->normalizeKey($relativePath)));
    }

    public function publicUrl(string $relativePath): string
    {
        $key = $this->normalizeKey($relativePath);
        if ($this->publicBaseUrl !== '') {
            return rtrim($this->publicBaseUrl, '/') . '/' . $key;
        }

        return '/storage/' . $key;
    }

    public function signedUrl(string $relativePath, int $ttlSeconds): string
    {
        if (!$this->signedUrlsEnabled || !$this->signedUrlGenerator instanceof SignedUrlGenerator) {
            return $this->publicUrl($relativePath);
        }

        return $this->signedUrlGenerator->generate($relativePath, $ttlSeconds);
    }

    private function normalizeKey(string $relativePath): string
    {
        $key = str_replace('\\', '/', trim($relativePath));
        $key = ltrim($key, '/');
        if ($key === '' || str_contains($key, '..')) {
            throw new \InvalidArgumentException('Invalid storage key');
        }

        return $key;
    }

    private function absolutePath(string $normalizedKey): string
    {
        return rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedKey);
    }
}
