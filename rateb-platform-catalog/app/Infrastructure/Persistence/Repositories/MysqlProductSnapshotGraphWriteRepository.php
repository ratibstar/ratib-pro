<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphWriteRepositoryInterface;

final class MysqlProductSnapshotGraphWriteRepository extends BaseRepository implements ProductSnapshotGraphWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'products';
    }

    public function restoreForProduct(int $productId, string $productUuid, array $snapshot, ?int $actorId): void
    {
        $this->restoreVariants($productId, is_array($snapshot['variants'] ?? null) ? $snapshot['variants'] : [], $actorId);
        $this->restoreProductBarcodes($productId, is_array($snapshot['product_barcodes'] ?? null) ? $snapshot['product_barcodes'] : [], $actorId);
        $this->restoreBundleComponents($productUuid, is_array($snapshot['bundle_components'] ?? null) ? $snapshot['bundle_components'] : [], $actorId);
        $this->restoreImages($productId, is_array($snapshot['images'] ?? null) ? $snapshot['images'] : [], $actorId);
        $this->restoreFiles($productId, is_array($snapshot['files'] ?? null) ? $snapshot['files'] : [], $actorId);
        $this->restoreVideos($productId, is_array($snapshot['videos'] ?? null) ? $snapshot['videos'] : [], $actorId);
    }

    /**
     * @param list<array<string, mixed>> $variants
     */
    private function restoreVariants(int $productId, array $variants, ?int $actorId): void
    {
        $keepUuids = array_map(static fn (array $row): string => (string) $row['uuid'], $variants);
        $this->softDeleteMissingByUuid('product_variants', 'product_id', $productId, $keepUuids, $actorId);

        foreach ($variants as $variant) {
            $variantUuid = (string) $variant['uuid'];
            $existing = $this->fetchOne(
                'SELECT id FROM product_variants WHERE uuid = :uuid LIMIT 1',
                ['uuid' => $variantUuid],
                false
            );

            if ($existing !== null) {
                $variantId = (int) $existing['id'];
                $this->writePdo->prepare(
                    'UPDATE product_variants SET product_id = :product_id, sku = :sku, primary_barcode = :primary_barcode,
                     sort_order = :sort_order, weight_kg = :weight_kg, length_cm = :length_cm, width_cm = :width_cm,
                     height_cm = :height_cm, status = :status, is_default = :is_default,
                     deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE uuid = :uuid'
                )->execute([
                    'product_id' => $productId,
                    'uuid' => $variantUuid,
                    'sku' => (string) $variant['sku'],
                    'primary_barcode' => $variant['primary_barcode'] ?? null,
                    'sort_order' => (int) ($variant['sort_order'] ?? 0),
                    'weight_kg' => $variant['weight_kg'] ?? null,
                    'length_cm' => $variant['length_cm'] ?? null,
                    'width_cm' => $variant['width_cm'] ?? null,
                    'height_cm' => $variant['height_cm'] ?? null,
                    'status' => (string) ($variant['status'] ?? 'draft'),
                    'is_default' => (int) ($variant['is_default'] ?? 0),
                    'updated_by' => $actorId,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_variants (
                        uuid, product_id, sku, primary_barcode, sort_order,
                        weight_kg, length_cm, width_cm, height_cm, status, is_default, created_by
                     ) VALUES (
                        :uuid, :product_id, :sku, :primary_barcode, :sort_order,
                        :weight_kg, :length_cm, :width_cm, :height_cm, :status, :is_default, :created_by
                     )'
                )->execute([
                    'uuid' => $variantUuid,
                    'product_id' => $productId,
                    'sku' => (string) $variant['sku'],
                    'primary_barcode' => $variant['primary_barcode'] ?? null,
                    'sort_order' => (int) ($variant['sort_order'] ?? 0),
                    'weight_kg' => $variant['weight_kg'] ?? null,
                    'length_cm' => $variant['length_cm'] ?? null,
                    'width_cm' => $variant['width_cm'] ?? null,
                    'height_cm' => $variant['height_cm'] ?? null,
                    'status' => (string) ($variant['status'] ?? 'draft'),
                    'is_default' => (int) ($variant['is_default'] ?? 0),
                    'created_by' => $actorId,
                ]);
                $variantId = (int) $this->writePdo->lastInsertId();
            }

            $this->replaceVariantTranslations($variantId, is_array($variant['translations'] ?? null) ? $variant['translations'] : [], $actorId);
            $this->replaceVariantBarcodes($variantId, is_array($variant['barcodes'] ?? null) ? $variant['barcodes'] : [], $actorId);
            $this->replaceVariantOptions($variantId, is_array($variant['option_values'] ?? null) ? $variant['option_values'] : [], $actorId);
        }
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    private function replaceVariantTranslations(int $variantId, array $translations, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE product_variant_translations SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_variant_id = :variant_id AND deleted_at IS NULL'
        )->execute(['variant_id' => $variantId, 'actor_id' => $actorId]);

        foreach ($translations as $translation) {
            $this->writePdo->prepare(
                'INSERT INTO product_variant_translations
                    (uuid, product_variant_id, language_code, name, description, created_by)
                 VALUES (:uuid, :product_variant_id, :language_code, :name, :description, :created_by)'
            )->execute([
                'uuid' => $this->newUuid(),
                'product_variant_id' => $variantId,
                'language_code' => (string) $translation['language_code'],
                'name' => $translation['name'] ?? null,
                'description' => $translation['description'] ?? null,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $barcodes
     */
    private function replaceVariantBarcodes(int $variantId, array $barcodes, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE product_variant_barcodes SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_variant_id = :variant_id AND deleted_at IS NULL'
        )->execute(['variant_id' => $variantId, 'actor_id' => $actorId]);

        foreach ($barcodes as $barcode) {
            $barcodeUuid = isset($barcode['uuid']) ? (string) $barcode['uuid'] : $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_variant_barcodes
                    (uuid, product_variant_id, barcode, barcode_type, is_primary, created_by)
                 VALUES (:uuid, :product_variant_id, :barcode, :barcode_type, :is_primary, :created_by)'
            )->execute([
                'uuid' => $barcodeUuid,
                'product_variant_id' => $variantId,
                'barcode' => (string) $barcode['barcode'],
                'barcode_type' => (string) ($barcode['barcode_type'] ?? 'OTHER'),
                'is_primary' => (int) ($barcode['is_primary'] ?? 0),
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    private function replaceVariantOptions(int $variantId, array $options, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE variant_attributes SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_variant_id = :variant_id AND deleted_at IS NULL'
        )->execute(['variant_id' => $variantId, 'actor_id' => $actorId]);

        foreach ($options as $option) {
            $attributeId = $this->resolveRequiredId('attributes', (string) $option['attribute_uuid']);
            $valueId = $this->resolveRequiredId('attribute_values', (string) $option['attribute_value_uuid']);
            $this->writePdo->prepare(
                'INSERT INTO variant_attributes (uuid, product_variant_id, attribute_id, attribute_value_id, created_by)
                 VALUES (:uuid, :product_variant_id, :attribute_id, :attribute_value_id, :created_by)'
            )->execute([
                'uuid' => $this->newUuid(),
                'product_variant_id' => $variantId,
                'attribute_id' => $attributeId,
                'attribute_value_id' => $valueId,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $barcodes
     */
    private function restoreProductBarcodes(int $productId, array $barcodes, ?int $actorId): void
    {
        $keepUuids = array_map(static fn (array $row): string => (string) $row['uuid'], $barcodes);
        $this->softDeleteMissingByUuid('product_barcodes', 'product_id', $productId, $keepUuids, $actorId);

        foreach ($barcodes as $barcode) {
            $uuid = (string) $barcode['uuid'];
            $existing = $this->fetchOne('SELECT id FROM product_barcodes WHERE uuid = :uuid LIMIT 1', ['uuid' => $uuid], false);
            if ($existing !== null) {
                $this->writePdo->prepare(
                    'UPDATE product_barcodes SET product_id = :product_id, barcode = :barcode, barcode_type = :barcode_type,
                     is_primary = :is_primary, deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE uuid = :uuid'
                )->execute([
                    'product_id' => $productId,
                    'uuid' => $uuid,
                    'barcode' => (string) $barcode['barcode'],
                    'barcode_type' => (string) ($barcode['barcode_type'] ?? 'OTHER'),
                    'is_primary' => (int) ($barcode['is_primary'] ?? 0),
                    'updated_by' => $actorId,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_barcodes (uuid, product_id, barcode, barcode_type, is_primary, created_by)
                     VALUES (:uuid, :product_id, :barcode, :barcode_type, :is_primary, :created_by)'
                )->execute([
                    'uuid' => $uuid,
                    'product_id' => $productId,
                    'barcode' => (string) $barcode['barcode'],
                    'barcode_type' => (string) ($barcode['barcode_type'] ?? 'OTHER'),
                    'is_primary' => (int) ($barcode['is_primary'] ?? 0),
                    'created_by' => $actorId,
                ]);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $components
     */
    private function restoreBundleComponents(string $productUuid, array $components, ?int $actorId): void
    {
        $bundle = $this->fetchOne(
            'SELECT id, is_bundle FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $productUuid],
            false
        );
        if ($bundle === null || (int) ($bundle['is_bundle'] ?? 0) !== 1 || $components === []) {
            return;
        }

        $bundleProductId = (int) $bundle['id'];
        $keepUuids = array_map(static fn (array $row): string => (string) $row['uuid'], $components);
        $this->softDeleteMissingByUuid('product_bundles', 'bundle_product_id', $bundleProductId, $keepUuids, $actorId);

        foreach ($components as $component) {
            $componentProductId = $this->resolveRequiredId('products', (string) $component['component_product_uuid']);
            $variantId = null;
            if (!empty($component['component_variant_uuid'])) {
                $variantId = $this->resolveVariantIdForProduct((string) $component['component_variant_uuid'], $componentProductId);
            }

            $uuid = (string) $component['uuid'];
            $existing = $this->fetchOne('SELECT id FROM product_bundles WHERE uuid = :uuid LIMIT 1', ['uuid' => $uuid], false);
            if ($existing !== null) {
                $this->writePdo->prepare(
                    'UPDATE product_bundles SET bundle_product_id = :bundle_product_id, component_product_id = :component_product_id,
                     component_variant_id = :component_variant_id, quantity = :quantity, sort_order = :sort_order,
                     is_optional = :is_optional, deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE uuid = :uuid'
                )->execute([
                    'bundle_product_id' => $bundleProductId,
                    'component_product_id' => $componentProductId,
                    'component_variant_id' => $variantId,
                    'quantity' => $component['quantity'] ?? 1,
                    'sort_order' => (int) ($component['sort_order'] ?? 0),
                    'is_optional' => (int) ($component['is_optional'] ?? 0),
                    'updated_by' => $actorId,
                    'uuid' => $uuid,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_bundles
                        (uuid, bundle_product_id, component_product_id, component_variant_id, quantity, sort_order, is_optional, created_by)
                     VALUES
                        (:uuid, :bundle_product_id, :component_product_id, :component_variant_id, :quantity, :sort_order, :is_optional, :created_by)'
                )->execute([
                    'uuid' => $uuid,
                    'bundle_product_id' => $bundleProductId,
                    'component_product_id' => $componentProductId,
                    'component_variant_id' => $variantId,
                    'quantity' => $component['quantity'] ?? 1,
                    'sort_order' => (int) ($component['sort_order'] ?? 0),
                    'is_optional' => (int) ($component['is_optional'] ?? 0),
                    'created_by' => $actorId,
                ]);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $images
     */
    private function restoreImages(int $productId, array $images, ?int $actorId): void
    {
        $keepUuids = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['uuid'], $images)));
        $this->softDeleteMissingImages($productId, $keepUuids, $actorId);

        foreach ($images as $image) {
            $assetTypeId = $this->resolveAssetTypeId((string) ($image['asset_type_code'] ?? 'product_image'));
            $uuid = (string) $image['uuid'];
            $variant = (string) ($image['variant'] ?? 'original');
            $existing = $this->fetchOne(
                'SELECT id FROM product_images WHERE uuid = :uuid AND variant = :variant LIMIT 1',
                ['uuid' => $uuid, 'variant' => $variant],
                false
            );

            if ($existing !== null) {
                $imageId = (int) $existing['id'];
                $this->writePdo->prepare(
                    'UPDATE product_images SET product_id = :product_id, asset_type_id = :asset_type_id, storage_key = :storage_key,
                     mime_type = :mime_type, width = :width, height = :height, file_size_bytes = :file_size_bytes,
                     variant = :variant, sort_order = :sort_order, is_primary = :is_primary, optimized = :optimized,
                     compressed = :compressed, checksum_sha256 = :checksum_sha256,
                     deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE id = :id'
                )->execute([
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'storage_key' => (string) $image['storage_key'],
                    'mime_type' => $image['mime_type'] ?? null,
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    'file_size_bytes' => $image['file_size_bytes'] ?? null,
                    'variant' => $variant,
                    'sort_order' => (int) ($image['sort_order'] ?? 0),
                    'is_primary' => (int) ($image['is_primary'] ?? 0),
                    'optimized' => (int) ($image['optimized'] ?? 0),
                    'compressed' => (int) ($image['compressed'] ?? 0),
                    'checksum_sha256' => $image['checksum_sha256'] ?? null,
                    'updated_by' => $actorId,
                    'id' => $imageId,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_images (
                        uuid, product_id, asset_type_id, storage_key, mime_type, width, height, file_size_bytes,
                        variant, sort_order, is_primary, optimized, compressed, checksum_sha256, created_by
                     ) VALUES (
                        :uuid, :product_id, :asset_type_id, :storage_key, :mime_type, :width, :height, :file_size_bytes,
                        :variant, :sort_order, :is_primary, :optimized, :compressed, :checksum_sha256, :created_by
                     )'
                )->execute([
                    'uuid' => $uuid,
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'storage_key' => (string) $image['storage_key'],
                    'mime_type' => $image['mime_type'] ?? null,
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    'file_size_bytes' => $image['file_size_bytes'] ?? null,
                    'variant' => $variant,
                    'sort_order' => (int) ($image['sort_order'] ?? 0),
                    'is_primary' => (int) ($image['is_primary'] ?? 0),
                    'optimized' => (int) ($image['optimized'] ?? 0),
                    'compressed' => (int) ($image['compressed'] ?? 0),
                    'checksum_sha256' => $image['checksum_sha256'] ?? null,
                    'created_by' => $actorId,
                ]);
                $imageId = (int) $this->writePdo->lastInsertId();
            }

            $this->replaceImageTranslations($imageId, is_array($image['translations'] ?? null) ? $image['translations'] : [], $actorId);
        }
    }

    /**
     * @param list<array<string, mixed>> $files
     */
    private function restoreFiles(int $productId, array $files, ?int $actorId): void
    {
        $keepUuids = array_map(static fn (array $row): string => (string) $row['uuid'], $files);
        $this->softDeleteMissingByUuid('product_files', 'product_id', $productId, $keepUuids, $actorId);

        foreach ($files as $file) {
            $assetTypeId = $this->resolveAssetTypeId((string) ($file['asset_type_code'] ?? 'product_file'));
            $uuid = (string) $file['uuid'];
            $existing = $this->fetchOne('SELECT id FROM product_files WHERE uuid = :uuid LIMIT 1', ['uuid' => $uuid], false);
            if ($existing !== null) {
                $fileId = (int) $existing['id'];
                $this->writePdo->prepare(
                    'UPDATE product_files SET product_id = :product_id, asset_type_id = :asset_type_id, storage_key = :storage_key,
                     mime_type = :mime_type, file_size_bytes = :file_size_bytes, checksum_sha256 = :checksum_sha256,
                     sort_order = :sort_order, deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE uuid = :uuid'
                )->execute([
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'storage_key' => (string) $file['storage_key'],
                    'mime_type' => $file['mime_type'] ?? null,
                    'file_size_bytes' => $file['file_size_bytes'] ?? null,
                    'checksum_sha256' => $file['checksum_sha256'] ?? null,
                    'sort_order' => (int) ($file['sort_order'] ?? 0),
                    'updated_by' => $actorId,
                    'uuid' => $uuid,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_files
                        (uuid, product_id, asset_type_id, storage_key, mime_type, file_size_bytes, checksum_sha256, sort_order, created_by)
                     VALUES
                        (:uuid, :product_id, :asset_type_id, :storage_key, :mime_type, :file_size_bytes, :checksum_sha256, :sort_order, :created_by)'
                )->execute([
                    'uuid' => $uuid,
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'storage_key' => (string) $file['storage_key'],
                    'mime_type' => $file['mime_type'] ?? null,
                    'file_size_bytes' => $file['file_size_bytes'] ?? null,
                    'checksum_sha256' => $file['checksum_sha256'] ?? null,
                    'sort_order' => (int) ($file['sort_order'] ?? 0),
                    'created_by' => $actorId,
                ]);
                $fileId = (int) $this->writePdo->lastInsertId();
            }

            $this->replaceFileTranslations($fileId, is_array($file['translations'] ?? null) ? $file['translations'] : [], $actorId);
        }
    }

    /**
     * @param list<array<string, mixed>> $videos
     */
    private function restoreVideos(int $productId, array $videos, ?int $actorId): void
    {
        $keepUuids = array_map(static fn (array $row): string => (string) $row['uuid'], $videos);
        $this->softDeleteMissingByUuid('product_videos', 'product_id', $productId, $keepUuids, $actorId);

        foreach ($videos as $video) {
            $assetTypeId = $this->resolveAssetTypeId((string) ($video['asset_type_code'] ?? 'product_video'));
            $uuid = (string) $video['uuid'];
            $existing = $this->fetchOne('SELECT id FROM product_videos WHERE uuid = :uuid LIMIT 1', ['uuid' => $uuid], false);
            if ($existing !== null) {
                $videoId = (int) $existing['id'];
                $this->writePdo->prepare(
                    'UPDATE product_videos SET product_id = :product_id, asset_type_id = :asset_type_id, video_type = :video_type,
                     external_id = :external_id, external_url = :external_url, storage_key = :storage_key,
                     thumbnail_storage_key = :thumbnail_storage_key, duration_seconds = :duration_seconds, sort_order = :sort_order,
                     deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by
                     WHERE uuid = :uuid'
                )->execute([
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'video_type' => (string) ($video['video_type'] ?? 'upload'),
                    'external_id' => $video['external_id'] ?? null,
                    'external_url' => $video['external_url'] ?? null,
                    'storage_key' => $video['storage_key'] ?? null,
                    'thumbnail_storage_key' => $video['thumbnail_storage_key'] ?? null,
                    'duration_seconds' => $video['duration_seconds'] ?? null,
                    'sort_order' => (int) ($video['sort_order'] ?? 0),
                    'updated_by' => $actorId,
                    'uuid' => $uuid,
                ]);
            } else {
                $this->writePdo->prepare(
                    'INSERT INTO product_videos (
                        uuid, product_id, asset_type_id, video_type, external_id, external_url, storage_key,
                        thumbnail_storage_key, duration_seconds, sort_order, created_by
                     ) VALUES (
                        :uuid, :product_id, :asset_type_id, :video_type, :external_id, :external_url, :storage_key,
                        :thumbnail_storage_key, :duration_seconds, :sort_order, :created_by
                     )'
                )->execute([
                    'uuid' => $uuid,
                    'product_id' => $productId,
                    'asset_type_id' => $assetTypeId,
                    'video_type' => (string) ($video['video_type'] ?? 'upload'),
                    'external_id' => $video['external_id'] ?? null,
                    'external_url' => $video['external_url'] ?? null,
                    'storage_key' => $video['storage_key'] ?? null,
                    'thumbnail_storage_key' => $video['thumbnail_storage_key'] ?? null,
                    'duration_seconds' => $video['duration_seconds'] ?? null,
                    'sort_order' => (int) ($video['sort_order'] ?? 0),
                    'created_by' => $actorId,
                ]);
                $videoId = (int) $this->writePdo->lastInsertId();
            }

            $this->replaceVideoTranslations($videoId, is_array($video['translations'] ?? null) ? $video['translations'] : [], $actorId);
        }
    }

    /**
     * @param list<string> $keepUuids
     */
    private function softDeleteMissingByUuid(string $table, string $parentColumn, int $parentId, array $keepUuids, ?int $actorId): void
    {
        if ($keepUuids === []) {
            $this->writePdo->prepare(
                "UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
                 WHERE {$parentColumn} = :parent_id AND deleted_at IS NULL"
            )->execute(['parent_id' => $parentId, 'actor_id' => $actorId]);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($keepUuids), '?'));
        $params = array_merge([$actorId, $parentId], $keepUuids);
        $this->writePdo->prepare(
            "UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = ?
             WHERE {$parentColumn} = ? AND deleted_at IS NULL AND uuid NOT IN ({$placeholders})"
        )->execute($params);
    }

    /**
     * @param list<string> $keepUuids
     */
    private function softDeleteMissingImages(int $productId, array $keepUuids, ?int $actorId): void
    {
        if ($keepUuids === []) {
            $this->writePdo->prepare(
                'UPDATE product_images SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
                 WHERE product_id = :product_id AND deleted_at IS NULL'
            )->execute(['product_id' => $productId, 'actor_id' => $actorId]);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($keepUuids), '?'));
        $params = array_merge([$actorId, $productId], $keepUuids);
        $this->writePdo->prepare(
            'UPDATE product_images SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = ?
             WHERE product_id = ? AND deleted_at IS NULL AND uuid NOT IN (' . $placeholders . ')'
        )->execute($params);
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    private function replaceImageTranslations(int $imageId, array $translations, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE product_image_translations SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_image_id = :image_id AND deleted_at IS NULL'
        )->execute(['image_id' => $imageId, 'actor_id' => $actorId]);

        foreach ($translations as $translation) {
            $this->writePdo->prepare(
                'INSERT INTO product_image_translations (uuid, product_image_id, language_code, alt_text, created_by)
                 VALUES (:uuid, :product_image_id, :language_code, :alt_text, :created_by)'
            )->execute([
                'uuid' => $this->newUuid(),
                'product_image_id' => $imageId,
                'language_code' => (string) $translation['language_code'],
                'alt_text' => $translation['alt_text'] ?? null,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    private function replaceFileTranslations(int $fileId, array $translations, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE product_file_translations SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_file_id = :file_id AND deleted_at IS NULL'
        )->execute(['file_id' => $fileId, 'actor_id' => $actorId]);

        foreach ($translations as $translation) {
            $this->writePdo->prepare(
                'INSERT INTO product_file_translations
                    (uuid, product_file_id, language_code, title, description, created_by)
                 VALUES (:uuid, :product_file_id, :language_code, :title, :description, :created_by)'
            )->execute([
                'uuid' => $this->newUuid(),
                'product_file_id' => $fileId,
                'language_code' => (string) $translation['language_code'],
                'title' => $translation['title'] ?? null,
                'description' => $translation['description'] ?? null,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    private function replaceVideoTranslations(int $videoId, array $translations, ?int $actorId): void
    {
        $this->writePdo->prepare(
            'UPDATE product_video_translations SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
             WHERE product_video_id = :video_id AND deleted_at IS NULL'
        )->execute(['video_id' => $videoId, 'actor_id' => $actorId]);

        foreach ($translations as $translation) {
            $this->writePdo->prepare(
                'INSERT INTO product_video_translations
                    (uuid, product_video_id, language_code, title, description, created_by)
                 VALUES (:uuid, :product_video_id, :language_code, :title, :description, :created_by)'
            )->execute([
                'uuid' => $this->newUuid(),
                'product_video_id' => $videoId,
                'language_code' => (string) $translation['language_code'],
                'title' => $translation['title'] ?? null,
                'description' => $translation['description'] ?? null,
                'created_by' => $actorId,
            ]);
        }
    }

    private function resolveRequiredId(string $table, string $uuid): int
    {
        $row = $this->fetchOne(
            "SELECT id FROM {$table} WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1",
            ['uuid' => $uuid],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException("{$table} record not found: {$uuid}");
        }

        return (int) $row['id'];
    }

    private function resolveVariantIdForProduct(string $variantUuid, int $productId): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM product_variants WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $variantUuid, 'product_id' => $productId],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Variant not found for product: ' . $variantUuid);
        }

        return (int) $row['id'];
    }

    private function resolveAssetTypeId(string $code): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM asset_types WHERE code = :code AND deleted_at IS NULL LIMIT 1',
            ['code' => $code],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Asset type not found: ' . $code);
        }

        return (int) $row['id'];
    }
}
