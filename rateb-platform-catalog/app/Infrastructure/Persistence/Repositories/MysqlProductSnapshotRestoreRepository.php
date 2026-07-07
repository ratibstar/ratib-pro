<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotRestoreRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationWriteRepositoryInterface;

final class MysqlProductSnapshotRestoreRepository extends BaseRepository implements ProductSnapshotRestoreRepositoryInterface
{
    public function __construct(
        ?\PDO $readPdo = null,
        ?\PDO $writePdo = null,
        private readonly ?ProductTranslationWriteRepositoryInterface $translationWriter = null,
        private readonly ?ProductAttributeWriteRepositoryInterface $attributeWriter = null,
        private readonly ?ProductRelationWriteRepositoryInterface $relationWriter = null,
        private readonly ?ProductSeoWriteRepositoryInterface $seoWriter = null,
        private readonly ?ProductSnapshotGraphWriteRepositoryInterface $graphWriter = null
    ) {
        parent::__construct($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'products';
    }

    public function restore(
        string $productUuid,
        array $snapshot,
        int $expectedLockVersion,
        ?int $actorId,
        string $changeSummary
    ): array {
        return $this->transaction(function () use ($productUuid, $snapshot, $expectedLockVersion, $actorId, $changeSummary): array {
            $product = $this->fetchOne(
                'SELECT id, lock_version, version_number FROM products
                 WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $productUuid],
                false
            );
            if ($product === null) {
                throw new \RuntimeException('Product not found', 404);
            }
            if ((int) $product['lock_version'] !== $expectedLockVersion) {
                throw new \RuntimeException('version_conflict', 409);
            }

            $productData = is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [];
            $translations = is_array($snapshot['translations'] ?? null) ? $snapshot['translations'] : [];
            $attributes = is_array($snapshot['attributes'] ?? null) ? $snapshot['attributes'] : [];
            $relations = is_array($snapshot['relations'] ?? null) ? $snapshot['relations'] : [];
            $seoData = $this->resolveSeoSnapshot($snapshot);

            $this->applyProductUpdate($productUuid, (int) $product['id'], $productData, $expectedLockVersion, $actorId);
            if ($translations !== [] && $this->translationWriter !== null) {
                $this->translationWriter->upsertForProduct((int) $product['id'], $translations, $actorId);
            }
            if ($attributes !== [] && $this->attributeWriter !== null) {
                $this->attributeWriter->replaceForProduct($productUuid, $attributes, $actorId);
            }
            if ($relations !== [] && $this->relationWriter !== null) {
                $this->relationWriter->replaceForProduct($productUuid, $relations, $actorId);
            }
            if ($seoData !== null && $this->seoWriter !== null) {
                $this->seoWriter->replaceFromSnapshot($productUuid, $seoData, $actorId);
            }
            if ($this->graphWriter !== null) {
                $this->graphWriter->restoreForProduct((int) $product['id'], $productUuid, $snapshot, $actorId);
            }

            $stmt = $this->writePdo->prepare(
                'UPDATE products SET version_number = version_number + 1, updated_by = :actor_id
                 WHERE uuid = :uuid AND deleted_at IS NULL'
            );
            $stmt->execute(['uuid' => $productUuid, 'actor_id' => $actorId]);

            $refreshed = $this->fetchOne(
                'SELECT id, version_number, lock_version FROM products WHERE uuid = :uuid LIMIT 1',
                ['uuid' => $productUuid],
                false
            );

            $versionNumber = (int) ($refreshed['version_number'] ?? $product['version_number']);
            $snapshot['entity_version'] = $versionNumber;
            $versionUuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_versions
                 (uuid, product_id, version_number, change_type, change_summary, snapshot_json, entity_version, created_by)
                 VALUES (:uuid, :product_id, :version_number, :change_type, :change_summary, :snapshot_json, :entity_version, :created_by)'
            )->execute([
                'uuid' => $versionUuid,
                'product_id' => (int) $product['id'],
                'version_number' => $versionNumber,
                'change_type' => 'restore',
                'change_summary' => $changeSummary,
                'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: '{}',
                'entity_version' => $versionNumber,
                'created_by' => $actorId,
            ]);

            return [
                'version_number' => $versionNumber,
                'lock_version' => (int) ($refreshed['lock_version'] ?? $expectedLockVersion + 1),
                'product_id' => (int) $product['id'],
                'version_uuid' => $versionUuid,
            ];
        });
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function applyProductUpdate(
        string $productUuid,
        int $productId,
        array $productData,
        int $expectedLockVersion,
        ?int $actorId
    ): void {
        unset($productId);
        $sets = ['lock_version = lock_version + 1', 'updated_by = :updated_by'];
        $params = ['uuid' => $productUuid, 'updated_by' => $actorId, 'expected_lock' => $expectedLockVersion];

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
                $value = $productData[$key];
                if ($key === 'is_bundle') {
                    $value = (int) (bool) $value;
                }
                $sets[] = $column . ' = :' . $key;
                $params[$key] = $value;
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
                WHERE uuid = :uuid AND lock_version = :expected_lock AND deleted_at IS NULL';
        $stmt = $this->writePdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('version_conflict', 409);
        }
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

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function resolveSeoSnapshot(array $snapshot): ?array
    {
        if (isset($snapshot['seo']) && is_array($snapshot['seo'])) {
            return $snapshot['seo'];
        }

        $legacy = is_array($snapshot['seo_translations'] ?? null) ? $snapshot['seo_translations'] : [];
        if ($legacy === []) {
            return null;
        }

        $translations = [];
        foreach ($legacy as $localeKey => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['language_code']) && is_string($localeKey)) {
                $row['language_code'] = $localeKey;
            }
            $translations[] = $row;
        }

        return [
            'canonical_url' => null,
            'translations' => $translations,
        ];
    }
}
