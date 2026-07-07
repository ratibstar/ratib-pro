<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class S3CompatibleAdapter implements StorageAdapterInterface
{
    public function put(string $relativePath, mixed $content, array $meta = []): StoredObject
    {
        unset($relativePath, $content, $meta);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }

    public function get(string $relativePath)
    {
        unset($relativePath);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }

    public function delete(string $relativePath): void
    {
        unset($relativePath);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }

    public function exists(string $relativePath): bool
    {
        unset($relativePath);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }

    public function publicUrl(string $relativePath): string
    {
        unset($relativePath);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }

    public function signedUrl(string $relativePath, int $ttlSeconds): string
    {
        unset($relativePath, $ttlSeconds);
        throw new \LogicException('S3CompatibleAdapter is not implemented in Phase 2.6');
    }
}
