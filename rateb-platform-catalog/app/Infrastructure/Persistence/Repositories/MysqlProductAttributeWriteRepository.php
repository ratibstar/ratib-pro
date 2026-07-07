<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeWriteRepositoryInterface;

final class MysqlProductAttributeWriteRepository extends BaseRepository implements ProductAttributeWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_attributes';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use replaceForProduct');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Use replaceForProduct');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Use replaceForProduct');
    }

    public function replaceForProduct(string $productUuid, array $attributes, ?int $actorId = null): void
    {
        $this->transaction(function () use ($productUuid, $attributes, $actorId): void {
            $productId = $this->resolveProductIdByUuid($productUuid);

            $this->writePdo->prepare(
                'UPDATE product_attribute_translations pat
                 INNER JOIN product_attributes pa ON pa.id = pat.product_attribute_id
                 SET pat.deleted_at = CURRENT_TIMESTAMP(6), pat.deleted_by = :deleted_by
                 WHERE pa.product_id = :product_id AND pat.deleted_at IS NULL'
            )->execute(['product_id' => $productId, 'deleted_by' => $actorId]);

            $this->writePdo->prepare(
                'UPDATE product_attributes SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE product_id = :product_id AND deleted_at IS NULL'
            )->execute(['product_id' => $productId, 'deleted_by' => $actorId]);

            foreach ($attributes as $attribute) {
                $attributeId = $this->resolveRequiredId('attributes', (string) $attribute['attribute_uuid']);
                $valueId = null;
                if (isset($attribute['attribute_value_uuid']) && $attribute['attribute_value_uuid'] !== null) {
                    $valueId = $this->resolveRequiredId('attribute_values', (string) $attribute['attribute_value_uuid']);
                    $this->assertAttributeValueBelongsToAttribute($attributeId, $valueId);
                }

                $this->writePdo->prepare(
                    'INSERT INTO product_attributes (
                        uuid, product_id, attribute_id, attribute_value_id,
                        value_text, value_number, value_boolean, created_by
                     ) VALUES (
                        :uuid, :product_id, :attribute_id, :attribute_value_id,
                        :value_text, :value_number, :value_boolean, :created_by
                     )'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'product_id' => $productId,
                    'attribute_id' => $attributeId,
                    'attribute_value_id' => $valueId,
                    'value_text' => $attribute['value_text'] ?? null,
                    'value_number' => $attribute['value_number'] ?? null,
                    'value_boolean' => isset($attribute['value_boolean']) ? (int) $attribute['value_boolean'] : null,
                    'created_by' => $actorId,
                ]);

                $productAttributeId = (int) $this->writePdo->lastInsertId();
                foreach ($attribute['translations'] ?? [] as $translation) {
                    $this->writePdo->prepare(
                        'INSERT INTO product_attribute_translations
                            (uuid, product_attribute_id, language_code, value_text, created_by)
                         VALUES (:uuid, :product_attribute_id, :language_code, :value_text, :created_by)'
                    )->execute([
                        'uuid' => $this->newUuid(),
                        'product_attribute_id' => $productAttributeId,
                        'language_code' => (string) $translation['language_code'],
                        'value_text' => (string) $translation['value_text'],
                        'created_by' => $actorId,
                    ]);
                }
            }
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
