<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

interface StorageAdapterInterface
{
    /**
     * @param resource|string $content
     * @param array<string, mixed> $meta
     */
    public function put(string $relativePath, mixed $content, array $meta = []): StoredObject;

    /**
     * @return resource
     */
    public function get(string $relativePath);

    public function delete(string $relativePath): void;

    public function exists(string $relativePath): bool;

    public function publicUrl(string $relativePath): string;

    public function signedUrl(string $relativePath, int $ttlSeconds): string;
}
