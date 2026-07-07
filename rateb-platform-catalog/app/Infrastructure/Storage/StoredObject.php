<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class StoredObject
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $storageKey,
        public readonly int $sizeBytes,
        public readonly string $mimeType,
        public readonly array $meta = []
    ) {
    }
}
