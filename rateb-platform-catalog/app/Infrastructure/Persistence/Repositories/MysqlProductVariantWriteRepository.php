<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\Validators\SkuBarcodeUniquenessValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantWriteRepositoryInterface;

final class MysqlProductVariantWriteRepository extends BaseRepository implements ProductVariantWriteRepositoryInterface
{
    private readonly SkuBarcodeUniquenessValidator $uniqueness;

    public function __construct(?\PDO $readPdo = null, ?\PDO $writePdo = null)
    {
        parent::__construct($readPdo, $writePdo);
        $this->uniqueness = new SkuBarcodeUniquenessValidator(
            new MysqlSkuUniquenessReadRepository($readPdo, $writePdo),
            new MysqlBarcodeUniquenessReadRepository($readPdo, $writePdo)
        );
    }

    protected function table(): string
    {
        return 'product_variants';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use createForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Variant updates are not supported in Phase 2.5');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Variant delete is not supported in Phase 2.5');
    }

    public function createForProduct(
        string $productUuid,
        array $data,
        array $translations,
        array $barcodes,
        array $optionValues,
        ?int $actorId = null
    ): string {
        return $this->transaction(function () use ($productUuid, $data, $translations, $barcodes, $optionValues, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $sku = (string) $data['sku'];
            $this->uniqueness->assertSkuAvailable($sku);

            $uuid = $this->newUuid();
            $primaryBarcode = $data['primary_barcode'] ?? null;

            $stmt = $this->writePdo->prepare(
                'INSERT INTO product_variants (
                    uuid, product_id, sku, primary_barcode, sort_order,
                    weight_kg, length_cm, width_cm, height_cm, status, is_default, created_by
                 ) VALUES (
                    :uuid, :product_id, :sku, :primary_barcode, :sort_order,
                    :weight_kg, :length_cm, :width_cm, :height_cm, :status, :is_default, :created_by
                 )'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'product_id' => $productId,
                'sku' => $sku,
                'primary_barcode' => $primaryBarcode,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'weight_kg' => $data['weight_kg'] ?? null,
                'length_cm' => $data['length_cm'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'status' => (string) ($data['status'] ?? 'draft'),
                'is_default' => (int) ($data['is_default'] ?? 0),
                'created_by' => $actorId,
            ]);

            $variantId = (int) $this->writePdo->lastInsertId();

            if ((int) ($data['is_default'] ?? 0) === 1) {
                $this->writePdo->prepare(
                    'UPDATE product_variants SET is_default = 0, updated_by = :updated_by
                     WHERE product_id = :product_id AND id <> :id AND deleted_at IS NULL'
                )->execute(['product_id' => $productId, 'id' => $variantId, 'updated_by' => $actorId]);
            }

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

            foreach ($barcodes as $barcodeRow) {
                $barcode = (string) $barcodeRow['barcode'];
                $this->uniqueness->assertBarcodeAvailable($barcode);
                $this->writePdo->prepare(
                    'INSERT INTO product_variant_barcodes
                        (uuid, product_variant_id, barcode, barcode_type, is_primary, created_by)
                     VALUES (:uuid, :product_variant_id, :barcode, :barcode_type, :is_primary, :created_by)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_variant_id' => $variantId,
                    'barcode' => $barcode,
                    'barcode_type' => (string) ($barcodeRow['barcode_type'] ?? 'OTHER'),
                    'is_primary' => (int) ($barcodeRow['is_primary'] ?? 0),
                    'created_by' => $actorId,
                ]);
            }

            foreach ($optionValues as $option) {
                $attributeId = $this->resolveRequiredId('attributes', (string) $option['attribute_uuid']);
                $valueId = $this->resolveRequiredId('attribute_values', (string) $option['attribute_value_uuid']);
                $this->assertAttributeValueBelongsToAttribute($attributeId, $valueId);

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

            return $uuid;
        });
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

    private function assertAttributeValueBelongsToAttribute(int $attributeId, int $valueId): void
    {
        $row = $this->fetchOne(
            'SELECT id FROM attribute_values
             WHERE id = :value_id AND attribute_id = :attribute_id AND deleted_at IS NULL LIMIT 1',
            ['value_id' => $valueId, 'attribute_id' => $attributeId],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Attribute value does not belong to attribute');
        }
    }
}
