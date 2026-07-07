<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductImageDto
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
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly int $fileSizeBytes,
        public readonly string $variant,
        public readonly int $sortOrder,
        public readonly bool $isPrimary,
        public readonly bool $optimized,
        public readonly ?string $checksumSha256,
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
            'width' => $this->width,
            'height' => $this->height,
            'file_size_bytes' => $this->fileSizeBytes,
            'variant' => $this->variant,
            'sort_order' => $this->sortOrder,
            'is_primary' => $this->isPrimary,
            'optimized' => $this->optimized,
            'checksum_sha256' => $this->checksumSha256,
            'translations' => $this->translations,
        ];
    }
}
