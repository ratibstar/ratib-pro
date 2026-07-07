<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Mappers\MediaMapper;
use Rateb\PlatformCatalog\Infrastructure\Storage\LocalStorageAdapter;

catalog_test('MediaMapper maps asset type', static function (): void {
    $dto = MediaMapper::toAssetTypeDto([
        'uuid' => 'at1',
        'code' => 'pdf',
        'category' => 'document',
        'is_system' => 1,
        'status' => 'active',
        'name' => 'PDF document',
    ]);

    catalog_assert_same('pdf', $dto->toArray()['code']);
});

catalog_test('MediaMapper maps product image with storage url', static function (): void {
    $storage = new LocalStorageAdapter(sys_get_temp_dir(), 'https://cdn.test');
    $dto = MediaMapper::toProductImageDto([
        'uuid' => 'img1',
        'asset_type_code' => 'image_original',
        'storage_key' => 'catalog/products/p1/images/img1/original.jpg',
        'mime_type' => 'image/jpeg',
        'width' => 100,
        'height' => 200,
        'file_size_bytes' => 5000,
        'variant' => 'original',
        'sort_order' => 0,
        'is_primary' => 1,
        'optimized' => 0,
        'checksum_sha256' => 'abc',
    ], $storage);

    catalog_assert_same('catalog/products/p1/images/img1/original.jpg', $dto->toArray()['storage_key']);
    catalog_assert_same('https://cdn.test/catalog/products/p1/images/img1/original.jpg', $dto->toArray()['url']);
});

catalog_test('MediaMapper maps external video metadata', static function (): void {
    $storage = new LocalStorageAdapter(sys_get_temp_dir());
    $dto = MediaMapper::toProductVideoDto([
        'uuid' => 'v1',
        'asset_type_code' => 'video_youtube',
        'video_type' => 'youtube',
        'external_id' => 'abc123',
        'external_url' => 'https://youtube.com/watch?v=abc123',
        'storage_key' => null,
        'thumbnail_storage_key' => null,
        'duration_seconds' => 120,
        'sort_order' => 0,
    ], $storage);

    catalog_assert_same('youtube', $dto->toArray()['video_type']);
    catalog_assert_same('https://youtube.com/watch?v=abc123', $dto->toArray()['url']);
});
