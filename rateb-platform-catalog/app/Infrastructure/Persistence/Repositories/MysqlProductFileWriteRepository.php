<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\Validators\MediaChecksumValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileWriteRepositoryInterface;

final class MysqlProductFileWriteRepository extends BaseRepository implements ProductFileWriteRepositoryInterface
{
    private readonly MediaChecksumValidator $checksumValidator;

    public function __construct(?\PDO $readPdo = null, ?\PDO $writePdo = null)
    {
        parent::__construct($readPdo, $writePdo);
        $this->checksumValidator = new MediaChecksumValidator($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'product_files';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use createForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('File updates are not supported in Phase 2.6');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Use removeForProduct');
    }

    public function createForProduct(
        string $productUuid,
        string $fileUuid,
        string $storageKey,
        array $metadata,
        array $translations,
        ?int $actorId = null
    ): string {
        return $this->transaction(function () use ($productUuid, $fileUuid, $storageKey, $metadata, $translations, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $assetTypeId = $this->resolveAssetTypeId((string) ($metadata['asset_type_code'] ?? 'pdf'));
            $checksum = isset($metadata['checksum_sha256']) ? (string) $metadata['checksum_sha256'] : null;
            $this->checksumValidator->assertFileChecksumAvailable($checksum);

            $this->writePdo->prepare(
                'INSERT INTO product_files (
                    uuid, product_id, asset_type_id, storage_key, mime_type,
                    file_size_bytes, checksum_sha256, sort_order, created_by
                 ) VALUES (
                    :uuid, :product_id, :asset_type_id, :storage_key, :mime_type,
                    :file_size_bytes, :checksum_sha256, :sort_order, :created_by
                 )'
            )->execute([
                'uuid' => $fileUuid,
                'product_id' => $productId,
                'asset_type_id' => $assetTypeId,
                'storage_key' => $storageKey,
                'mime_type' => (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
                'file_size_bytes' => (int) ($metadata['file_size_bytes'] ?? 0),
                'checksum_sha256' => $checksum,
                'sort_order' => (int) ($metadata['sort_order'] ?? 0),
                'created_by' => $actorId,
            ]);

            $fileId = (int) $this->writePdo->lastInsertId();
            foreach ($translations as $translation) {
                $this->writePdo->prepare(
                    'INSERT INTO product_file_translations (uuid, product_file_id, language_code, title, description, created_by)
                     VALUES (:uuid, :product_file_id, :language_code, :title, :description, :created_by)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_file_id' => $fileId,
                    'language_code' => (string) $translation['language_code'],
                    'title' => (string) $translation['title'],
                    'description' => $translation['description'] ?? null,
                    'created_by' => $actorId,
                ]);
            }

            return $fileUuid;
        });
    }

    public function removeForProduct(string $productUuid, string $fileUuid, ?int $actorId = null): bool
    {
        return $this->transaction(function () use ($productUuid, $fileUuid, $actorId): bool {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $this->writePdo->prepare(
                'UPDATE product_file_translations pft
                 INNER JOIN product_files pf ON pf.id = pft.product_file_id
                 SET pft.deleted_at = CURRENT_TIMESTAMP(6), pft.deleted_by = :deleted_by
                 WHERE pf.uuid = :uuid AND pf.product_id = :product_id AND pft.deleted_at IS NULL'
            )->execute(['uuid' => $fileUuid, 'product_id' => $productId, 'deleted_by' => $actorId]);

            $stmt = $this->writePdo->prepare(
                'UPDATE product_files SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL'
            );
            $stmt->execute(['uuid' => $fileUuid, 'product_id' => $productId, 'deleted_by' => $actorId]);

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
