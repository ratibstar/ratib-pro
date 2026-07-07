<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Mappers;

use Rateb\PlatformCatalog\Application\DTO\AssetTypeDto;
use Rateb\PlatformCatalog\Application\DTO\ProductFileDto;
use Rateb\PlatformCatalog\Application\DTO\ProductImageDto;
use Rateb\PlatformCatalog\Application\DTO\ProductVideoDto;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class MediaMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function toAssetTypeDto(array $row): AssetTypeDto
    {
        return new AssetTypeDto(
            uuid: (string) $row['uuid'],
            code: (string) $row['code'],
            category: (string) $row['category'],
            isSystem: (bool) (int) ($row['is_system'] ?? 0),
            status: (string) $row['status'],
            name: (string) ($row['name'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $translations
     */
    public static function toProductImageDto(
        array $row,
        StorageAdapterInterface $storage,
        array $translations = []
    ): ProductImageDto {
        $storageKey = (string) $row['storage_key'];

        return new ProductImageDto(
            uuid: (string) $row['uuid'],
            assetTypeCode: (string) $row['asset_type_code'],
            storageKey: $storageKey,
            url: $storage->publicUrl($storageKey),
            mimeType: (string) $row['mime_type'],
            width: isset($row['width']) ? (int) $row['width'] : null,
            height: isset($row['height']) ? (int) $row['height'] : null,
            fileSizeBytes: (int) ($row['file_size_bytes'] ?? 0),
            variant: (string) $row['variant'],
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isPrimary: (bool) (int) ($row['is_primary'] ?? 0),
            optimized: (bool) (int) ($row['optimized'] ?? 0),
            checksumSha256: isset($row['checksum_sha256']) ? (string) $row['checksum_sha256'] : null,
            translations: $translations
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $translations
     */
    public static function toProductFileDto(
        array $row,
        StorageAdapterInterface $storage,
        array $translations = []
    ): ProductFileDto {
        $storageKey = (string) $row['storage_key'];

        return new ProductFileDto(
            uuid: (string) $row['uuid'],
            assetTypeCode: (string) $row['asset_type_code'],
            storageKey: $storageKey,
            url: $storage->signedUrl($storageKey, 3600),
            mimeType: (string) $row['mime_type'],
            fileSizeBytes: (int) ($row['file_size_bytes'] ?? 0),
            checksumSha256: isset($row['checksum_sha256']) ? (string) $row['checksum_sha256'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            translations: $translations
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $translations
     */
    public static function toProductVideoDto(
        array $row,
        StorageAdapterInterface $storage,
        array $translations = []
    ): ProductVideoDto {
        $storageKey = isset($row['storage_key']) ? (string) $row['storage_key'] : null;
        $thumbKey = isset($row['thumbnail_storage_key']) ? (string) $row['thumbnail_storage_key'] : null;

        return new ProductVideoDto(
            uuid: (string) $row['uuid'],
            assetTypeCode: (string) $row['asset_type_code'],
            videoType: (string) $row['video_type'],
            externalId: isset($row['external_id']) ? (string) $row['external_id'] : null,
            externalUrl: isset($row['external_url']) ? (string) $row['external_url'] : null,
            storageKey: $storageKey,
            thumbnailStorageKey: $thumbKey,
            url: $storageKey !== null ? $storage->publicUrl($storageKey) : ($row['external_url'] ?? null),
            thumbnailUrl: $thumbKey !== null ? $storage->publicUrl($thumbKey) : null,
            durationSeconds: isset($row['duration_seconds']) ? (int) $row['duration_seconds'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            translations: $translations
        );
    }
}
