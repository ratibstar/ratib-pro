<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductVideoDto
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $assetTypeCode,
        public readonly string $videoType,
        public readonly ?string $externalId,
        public readonly ?string $externalUrl,
        public readonly ?string $storageKey,
        public readonly ?string $thumbnailStorageKey,
        public readonly ?string $url,
        public readonly ?string $thumbnailUrl,
        public readonly ?int $durationSeconds,
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
            'video_type' => $this->videoType,
            'external_id' => $this->externalId,
            'external_url' => $this->externalUrl,
            'storage_key' => $this->storageKey,
            'thumbnail_storage_key' => $this->thumbnailStorageKey,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnailUrl,
            'duration_seconds' => $this->durationSeconds,
            'sort_order' => $this->sortOrder,
            'translations' => $this->translations,
        ];
    }
}
