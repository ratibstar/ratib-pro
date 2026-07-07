<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductFileDto
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $assetTypeCode,
        public readonly string $storageKey,
        public readonly string $url,
        public readonly string $mimeType,
        public readonly int $fileSizeBytes,
        public readonly ?string $checksumSha256,
        public readonly int $sortOrder,
        public readonly array $translations = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'asset_type_code' => $this->assetTypeCode,
            'storage_key' => $this->storageKey,
            'url' => $this->url,
            'mime_type' => $this->mimeType,
            'file_size_bytes' => $this->fileSizeBytes,
            'checksum_sha256' => $this->checksumSha256,
            'sort_order' => $this->sortOrder,
            'translations' => $this->translations,
        ];
    }
}
