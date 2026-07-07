<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class MediaStorageKeyBuilder
{
    public static function productImage(string $productUuid, string $imageUuid, string $variant, string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');

        return sprintf(
            'catalog/products/%s/images/%s/%s.%s',
            $productUuid,
            $imageUuid,
            $variant,
            $extension
        );
    }

    public static function productFile(string $productUuid, string $fileUuid, string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');

        return sprintf('catalog/products/%s/files/%s.%s', $productUuid, $fileUuid, $extension);
    }

    public static function productVideo(string $productUuid, string $videoUuid, string $filename): string
    {
        $filename = basename($filename);

        return sprintf('catalog/products/%s/videos/%s/%s', $productUuid, $videoUuid, $filename);
    }
}
