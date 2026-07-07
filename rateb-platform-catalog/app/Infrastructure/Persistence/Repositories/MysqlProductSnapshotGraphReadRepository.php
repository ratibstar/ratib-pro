<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;

final class MysqlProductSnapshotGraphReadRepository extends BaseRepository implements ProductSnapshotGraphReadRepositoryInterface
{
    public function __construct(
        ?\PDO $readPdo = null,
        ?\PDO $writePdo = null,
        private readonly ?ProductVariantReadRepositoryInterface $variantReadRepository = null,
        private readonly ?ProductBarcodeReadRepositoryInterface $barcodeReadRepository = null,
        private readonly ?ProductBundleReadRepositoryInterface $bundleReadRepository = null,
        private readonly ?ProductImageReadRepositoryInterface $imageReadRepository = null,
        private readonly ?ProductFileReadRepositoryInterface $fileReadRepository = null,
        private readonly ?ProductVideoReadRepositoryInterface $videoReadRepository = null
    ) {
        parent::__construct($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'products';
    }

    public function buildForProduct(string $productUuid): array
    {
        $locale = new LocaleContext('en', 'ar');
        $variants = $this->variantReadRepository?->listByProductUuid($productUuid, $locale) ?? [];
        $variantIds = array_map(static fn (array $row): int => (int) $row['id'], $variants);
        $translationsByVariant = $this->listVariantTranslationsGrouped($variantIds);
        $barcodesByVariant = $this->variantReadRepository?->listBarcodesGroupedByVariantId($variantIds) ?? [];
        $optionsByVariant = $this->variantReadRepository?->listOptionValuesGroupedByVariantId($variantIds, $locale) ?? [];

        $variantSnapshots = [];
        foreach ($variants as $variant) {
            $variantId = (int) $variant['id'];
            $optionValues = [];
            foreach ($optionsByVariant[$variantId] ?? [] as $option) {
                $optionValues[] = [
                    'attribute_uuid' => $option['attribute_uuid'],
                    'attribute_value_uuid' => $option['attribute_value_uuid'],
                ];
            }

            $variantSnapshots[] = [
                'uuid' => $variant['uuid'],
                'sku' => $variant['sku'],
                'primary_barcode' => $variant['primary_barcode'] ?? null,
                'sort_order' => (int) ($variant['sort_order'] ?? 0),
                'weight_kg' => $variant['weight_kg'] ?? null,
                'length_cm' => $variant['length_cm'] ?? null,
                'width_cm' => $variant['width_cm'] ?? null,
                'height_cm' => $variant['height_cm'] ?? null,
                'status' => $variant['status'] ?? 'draft',
                'is_default' => (int) ($variant['is_default'] ?? 0),
                'translations' => $translationsByVariant[$variantId] ?? [],
                'barcodes' => $barcodesByVariant[$variantId] ?? [],
                'option_values' => $optionValues,
            ];
        }

        $images = $this->imageReadRepository?->listByProductUuid($productUuid, $locale) ?? [];
        $imageIds = array_map(static fn (array $row): int => (int) $row['id'], $images);
        $imageTranslations = $this->imageReadRepository?->listTranslationsGrouped($imageIds) ?? [];
        $imageSnapshots = [];
        foreach ($images as $image) {
            $imageId = (int) $image['id'];
            $imageSnapshots[] = [
                'uuid' => $image['uuid'],
                'storage_key' => $image['storage_key'],
                'mime_type' => $image['mime_type'] ?? null,
                'width' => $image['width'] ?? null,
                'height' => $image['height'] ?? null,
                'file_size_bytes' => $image['file_size_bytes'] ?? null,
                'variant' => $image['variant'] ?? 'original',
                'sort_order' => (int) ($image['sort_order'] ?? 0),
                'is_primary' => (int) ($image['is_primary'] ?? 0),
                'optimized' => (int) ($image['optimized'] ?? 0),
                'compressed' => (int) ($image['compressed'] ?? 0),
                'checksum_sha256' => $image['checksum_sha256'] ?? null,
                'asset_type_code' => $image['asset_type_code'] ?? null,
                'translations' => $imageTranslations[$imageId] ?? [],
            ];
        }

        $files = $this->fileReadRepository?->listByProductUuid($productUuid, $locale) ?? [];
        $fileIds = array_map(static fn (array $row): int => (int) $row['id'], $files);
        $fileTranslations = $this->fileReadRepository?->listTranslationsGrouped($fileIds) ?? [];
        $fileSnapshots = [];
        foreach ($files as $file) {
            $fileId = (int) $file['id'];
            $fileSnapshots[] = [
                'uuid' => $file['uuid'],
                'storage_key' => $file['storage_key'],
                'mime_type' => $file['mime_type'] ?? null,
                'file_size_bytes' => $file['file_size_bytes'] ?? null,
                'checksum_sha256' => $file['checksum_sha256'] ?? null,
                'sort_order' => (int) ($file['sort_order'] ?? 0),
                'asset_type_code' => $file['asset_type_code'] ?? null,
                'translations' => $fileTranslations[$fileId] ?? [],
            ];
        }

        $videos = $this->videoReadRepository?->listByProductUuid($productUuid, $locale) ?? [];
        $videoIds = array_map(static fn (array $row): int => (int) $row['id'], $videos);
        $videoTranslations = $this->videoReadRepository?->listTranslationsGrouped($videoIds) ?? [];
        $videoSnapshots = [];
        foreach ($videos as $video) {
            $videoId = (int) $video['id'];
            $videoSnapshots[] = [
                'uuid' => $video['uuid'],
                'video_type' => $video['video_type'] ?? 'upload',
                'external_id' => $video['external_id'] ?? null,
                'external_url' => $video['external_url'] ?? null,
                'storage_key' => $video['storage_key'] ?? null,
                'thumbnail_storage_key' => $video['thumbnail_storage_key'] ?? null,
                'duration_seconds' => $video['duration_seconds'] ?? null,
                'sort_order' => (int) ($video['sort_order'] ?? 0),
                'asset_type_code' => $video['asset_type_code'] ?? null,
                'translations' => $videoTranslations[$videoId] ?? [],
            ];
        }

        $bundleComponents = [];
        foreach ($this->bundleReadRepository?->listComponents($productUuid, $locale) ?? [] as $component) {
            $bundleComponents[] = [
                'uuid' => $component['uuid'],
                'component_product_uuid' => $component['component_product_uuid'],
                'component_variant_uuid' => $component['component_variant_uuid'] ?? null,
                'quantity' => $component['quantity'] ?? 1,
                'sort_order' => (int) ($component['sort_order'] ?? 0),
                'is_optional' => (int) ($component['is_optional'] ?? 0),
            ];
        }

        return [
            'variants' => $variantSnapshots,
            'product_barcodes' => $this->barcodeReadRepository?->listByProductUuid($productUuid) ?? [],
            'bundle_components' => $bundleComponents,
            'images' => $imageSnapshots,
            'files' => $fileSnapshots,
            'videos' => $videoSnapshots,
        ];
    }

    /**
     * @param list<int> $variantIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function listVariantTranslationsGrouped(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $inClause = [];
        $params = [];
        foreach ($variantIds as $index => $id) {
            $key = 'vid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT product_variant_id, language_code, name, description
             FROM product_variant_translations
             WHERE product_variant_id IN (' . implode(',', $inClause) . ') AND deleted_at IS NULL',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['product_variant_id'];
            $grouped[$id][] = [
                'language_code' => $row['language_code'],
                'name' => $row['name'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
