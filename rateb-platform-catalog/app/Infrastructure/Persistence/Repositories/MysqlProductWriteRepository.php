<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use PDO;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface;

final class MysqlProductWriteRepository extends BaseRepository implements ProductWriteRepositoryInterface
{
    public function __construct(
        ?PDO $readPdo = null,
        ?PDO $writePdo = null,
        private readonly ?ProductTranslationWriteRepositoryInterface $translationWriter = null
    ) {
        parent::__construct($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'products';
    }

    public function create(array $data): string
    {
        return $this->createWithTranslations($data, $data['translations'] ?? [], $data['actor_id'] ?? null);
    }

    public function createWithTranslations(array $productData, array $translations, ?int $actorId = null): string
    {
        $uuid = $this->newUuid();
        $categoryId = $this->resolveRequiredId('categories', (string) $productData['category_uuid']);
        $unitId = $this->resolveRequiredId('units', (string) $productData['unit_uuid']);
        $brandId = $this->resolveOptionalId('brands', $productData['brand_uuid'] ?? null);
        $familyId = $this->resolveOptionalId('product_families', $productData['family_uuid'] ?? null);

        $stmt = $this->writePdo->prepare(
            'INSERT INTO products (
                uuid, sku, brand_id, category_id, family_id, unit_id, is_bundle, primary_barcode,
                weight_kg, length_cm, width_cm, height_cm, manufacturer_id, country_id,
                warranty_months, tax_class, status, version_number, lock_version,
                search_weight, boost_score, created_by
             ) VALUES (
                :uuid, :sku, :brand_id, :category_id, :family_id, :unit_id, :is_bundle, :primary_barcode,
                :weight_kg, :length_cm, :width_cm, :height_cm, :manufacturer_id, :country_id,
                :warranty_months, :tax_class, :status, 1, 1,
                :search_weight, :boost_score, :created_by
             )'
        );

        $stmt->execute([
            'uuid' => $uuid,
            'sku' => (string) $productData['sku'],
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'family_id' => $familyId,
            'unit_id' => $unitId,
            'is_bundle' => (int) ($productData['is_bundle'] ?? 0),
            'primary_barcode' => $productData['primary_barcode'] ?? null,
            'weight_kg' => $productData['weight_kg'] ?? null,
            'length_cm' => $productData['length_cm'] ?? null,
            'width_cm' => $productData['width_cm'] ?? null,
            'height_cm' => $productData['height_cm'] ?? null,
            'manufacturer_id' => $productData['manufacturer_id'] ?? null,
            'country_id' => $productData['country_id'] ?? null,
            'warranty_months' => $productData['warranty_months'] ?? null,
            'tax_class' => $productData['tax_class'] ?? null,
            'status' => (string) ($productData['status'] ?? 'draft'),
            'search_weight' => $productData['search_weight'] ?? '1.0000',
            'boost_score' => $productData['boost_score'] ?? '0.0000',
            'created_by' => $actorId,
        ]);

        $productId = (int) $this->writePdo->lastInsertId();
        $this->translationWriter()?->upsertForProduct($productId, $translations, $actorId);

        return $uuid;
    }

    public function update(string $uuid, array $data): bool
    {
        $expected = (int) ($data['lock_version'] ?? 0);
        $this->updateWithTranslations($uuid, $data, $data['translations'] ?? [], $expected, $data['actor_id'] ?? null);

        return true;
    }

    public function updateWithTranslations(
        string $uuid,
        array $productData,
        array $translations,
        int $expectedLockVersion,
        ?int $actorId = null
    ): int {
        $current = $this->fetchOne(
            'SELECT id, lock_version FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid],
            false
        );

        if ($current === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        $actualLock = (int) $current['lock_version'];
        $productId = (int) $current['id'];
        $sets = ['lock_version = lock_version + 1', 'updated_by = :updated_by'];
        $params = ['uuid' => $uuid, 'updated_by' => $actorId];

        $map = [
            'sku' => 'sku',
            'primary_barcode' => 'primary_barcode',
            'weight_kg' => 'weight_kg',
            'length_cm' => 'length_cm',
            'width_cm' => 'width_cm',
            'height_cm' => 'height_cm',
            'manufacturer_id' => 'manufacturer_id',
            'country_id' => 'country_id',
            'warranty_months' => 'warranty_months',
            'tax_class' => 'tax_class',
            'status' => 'status',
            'search_weight' => 'search_weight',
            'boost_score' => 'boost_score',
            'is_bundle' => 'is_bundle',
        ];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $productData)) {
                $sets[] = $column . ' = :' . $key;
                $params[$key] = $productData[$key];
            }
        }

        if (isset($productData['category_uuid'])) {
            $sets[] = 'category_id = :category_id';
            $params['category_id'] = $this->resolveRequiredId('categories', (string) $productData['category_uuid']);
        }
        if (array_key_exists('brand_uuid', $productData)) {
            $sets[] = 'brand_id = :brand_id';
            $params['brand_id'] = $this->resolveOptionalId('brands', $productData['brand_uuid']);
        }
        if (array_key_exists('family_uuid', $productData)) {
            $sets[] = 'family_id = :family_id';
            $params['family_id'] = $this->resolveOptionalId('product_families', $productData['family_uuid']);
        }
        if (isset($productData['unit_uuid'])) {
            $sets[] = 'unit_id = :unit_id';
            $params['unit_id'] = $this->resolveRequiredId('units', (string) $productData['unit_uuid']);
        }

        $sql = 'UPDATE products SET ' . implode(', ', $sets) . '
                WHERE uuid = :uuid AND lock_version = :expected_lock_version AND deleted_at IS NULL';
        $params['expected_lock_version'] = $expectedLockVersion;
        $stmt = $this->writePdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('version_conflict', 409);
        }

        if ($translations !== []) {
            $this->translationWriter()?->upsertForProduct($productId, $translations, $actorId);
        }

        return $expectedLockVersion + 1;
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE products SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
             WHERE uuid = :uuid AND deleted_at IS NULL'
        );
        $stmt->execute(['uuid' => $uuid, 'deleted_by' => $actorId]);

        return $stmt->rowCount() > 0;
    }

    private function translationWriter(): ?ProductTranslationWriteRepositoryInterface
    {
        return $this->translationWriter;
    }

    private function resolveRequiredId(string $table, string $uuid): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM ' . $table . ' WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid],
            false
        );

        if ($row === null) {
            throw new \InvalidArgumentException('Invalid reference for table ' . $table . ': ' . $uuid);
        }

        return (int) $row['id'];
    }

    private function resolveOptionalId(string $table, mixed $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return $this->resolveRequiredId($table, (string) $uuid);
    }
}
