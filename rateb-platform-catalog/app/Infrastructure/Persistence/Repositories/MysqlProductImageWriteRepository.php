<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\Validators\MediaChecksumValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageWriteRepositoryInterface;

final class MysqlProductImageWriteRepository extends BaseRepository implements ProductImageWriteRepositoryInterface
{
    private readonly MediaChecksumValidator $checksumValidator;

    public function __construct(?\PDO $readPdo = null, ?\PDO $writePdo = null)
    {
        parent::__construct($readPdo, $writePdo);
        $this->checksumValidator = new MediaChecksumValidator(new MysqlMediaChecksumReadRepository($readPdo, $writePdo));
    }

    protected function table(): string
    {
        return 'product_images';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use createForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Image updates are not supported in Phase 2.6');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Use removeForProduct');
    }

    public function createForProduct(
        string $productUuid,
        string $imageUuid,
        string $storageKey,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string {
        return $this->transaction(function () use ($productUuid, $imageUuid, $storageKey, $metadata, $translations, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $assetTypeId = $this->resolveAssetTypeId((string) ($metadata['asset_type_code'] ?? 'image_original'));
            $checksum = isset($metadata['checksum_sha256']) ? (string) $metadata['checksum_sha256'] : null;
            $this->checksumValidator->assertImageChecksumAvailable($checksum);

            $variant = (string) ($metadata['variant'] ?? 'original');
            $this->writePdo->prepare(
                'INSERT INTO product_images (
                    uuid, product_id, asset_type_id, storage_key, mime_type, width, height,
                    file_size_bytes, variant, sort_order, is_primary, checksum_sha256, created_by
                 ) VALUES (
                    :uuid, :product_id, :asset_type_id, :storage_key, :mime_type, :width, :height,
                    :file_size_bytes, :variant, :sort_order, :is_primary, :checksum_sha256, :created_by
                 )'
            )->execute([
                'uuid' => $imageUuid,
                'product_id' => $productId,
                'asset_type_id' => $assetTypeId,
                'storage_key' => $storageKey,
                'mime_type' => (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
                'file_size_bytes' => (int) ($metadata['file_size_bytes'] ?? 0),
                'variant' => $variant,
                'sort_order' => (int) ($metadata['sort_order'] ?? 0),
                'is_primary' => (int) ($metadata['is_primary'] ?? 0),
                'checksum_sha256' => $checksum,
                'created_by' => $actorId,
            ]);

            $imageId = (int) $this->writePdo->lastInsertId();

            if ((int) ($metadata['is_primary'] ?? 0) === 1) {
                $this->writePdo->prepare(
                    'UPDATE product_images SET is_primary = 0, updated_by = :updated_by
                     WHERE product_id = :product_id AND id <> :id AND deleted_at IS NULL'
                )->execute(['product_id' => $productId, 'id' => $imageId, 'updated_by' => $actorId]);
            }

            foreach ($translations as $translation) {
                $this->writePdo->prepare(
                    'INSERT INTO product_image_translations (uuid, product_image_id, language_code, alt_text, created_by)
                     VALUES (:uuid, :product_image_id, :language_code, :alt_text, :created_by)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_image_id' => $imageId,
                    'language_code' => (string) $translation['language_code'],
                    'alt_text' => (string) ($translation['alt_text'] ?? ''),
                    'created_by' => $actorId,
                ]);
            }

            return $imageUuid;
        });
    }

    public function removeForProduct(string $productUuid, string $imageUuid, ?int $actorId = null): bool
    {
        return $this->transaction(function () use ($productUuid, $imageUuid, $actorId): bool {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $this->writePdo->prepare(
                'UPDATE product_image_translations pit
                 INNER JOIN product_images pi ON pi.id = pit.product_image_id
                 SET pit.deleted_at = CURRENT_TIMESTAMP(6), pit.deleted_by = :deleted_by
                 WHERE pi.uuid = :uuid AND pi.product_id = :product_id AND pit.deleted_at IS NULL'
            )->execute(['uuid' => $imageUuid, 'product_id' => $productId, 'deleted_by' => $actorId]);

            $stmt = $this->writePdo->prepare(
                'UPDATE product_images SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL'
            );
            $stmt->execute(['uuid' => $imageUuid, 'product_id' => $productId, 'deleted_by' => $actorId]);

            return $stmt->rowCount() > 0;
        });
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
