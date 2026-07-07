<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\Validators\SkuBarcodeUniquenessValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeWriteRepositoryInterface;

final class MysqlProductBarcodeWriteRepository extends BaseRepository implements ProductBarcodeWriteRepositoryInterface
{
    private readonly SkuBarcodeUniquenessValidator $uniqueness;

    public function __construct(?\PDO $readPdo = null, ?\PDO $writePdo = null)
    {
        parent::__construct($readPdo, $writePdo);
        $this->uniqueness = new SkuBarcodeUniquenessValidator($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'product_barcodes';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use addForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Product barcode updates are not supported in Phase 2.5');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Use removeForProduct with product context');
    }

    public function addForProduct(string $productUuid, array $data, ?int $actorId = null): string
    {
        return $this->transaction(function () use ($productUuid, $data, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $barcode = (string) $data['barcode'];
            $this->uniqueness->assertBarcodeAvailable($barcode);

            $uuid = $this->newUuid();
            $stmt = $this->writePdo->prepare(
                'INSERT INTO product_barcodes (uuid, product_id, barcode, barcode_type, is_primary, created_by)
                 VALUES (:uuid, :product_id, :barcode, :barcode_type, :is_primary, :created_by)'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'product_id' => $productId,
                'barcode' => $barcode,
                'barcode_type' => (string) ($data['barcode_type'] ?? 'OTHER'),
                'is_primary' => (int) ($data['is_primary'] ?? 0),
                'created_by' => $actorId,
            ]);

            if ((int) ($data['is_primary'] ?? 0) === 1) {
                $this->writePdo->prepare(
                    'UPDATE product_barcodes SET is_primary = 0, updated_by = :updated_by
                     WHERE product_id = :product_id AND uuid <> :uuid AND deleted_at IS NULL'
                )->execute(['product_id' => $productId, 'uuid' => $uuid, 'updated_by' => $actorId]);

                $this->writePdo->prepare(
                    'UPDATE products SET primary_barcode = :barcode, updated_by = :updated_by WHERE id = :id'
                )->execute(['barcode' => $barcode, 'updated_by' => $actorId, 'id' => $productId]);
            }

            return $uuid;
        });
    }

    public function removeForProduct(string $productUuid, string $barcodeUuid, ?int $actorId = null): bool
    {
        return $this->transaction(function () use ($productUuid, $barcodeUuid, $actorId): bool {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $stmt = $this->writePdo->prepare(
                'UPDATE product_barcodes SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL'
            );
            $stmt->execute(['uuid' => $barcodeUuid, 'product_id' => $productId, 'deleted_by' => $actorId]);

            return $stmt->rowCount() > 0;
        });
    }
}
