<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionWriteRepositoryInterface;

final class MysqlCollectionWriteRepository extends BaseRepository implements CollectionWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'collections';
    }

    public function create(array $data, array $translations): string
    {
        return $this->transaction(function () use ($data, $translations): string {
            $uuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO collections
                 (uuid, slug, collection_type, image_path, sort_order, status, publish_at, archive_at, created_by)
                 VALUES (:uuid, :slug, :collection_type, :image_path, :sort_order, :status, :publish_at, :archive_at, :created_by)'
            )->execute([
                'uuid' => $uuid,
                'slug' => (string) $data['slug'],
                'collection_type' => (string) ($data['collection_type'] ?? 'manual'),
                'image_path' => $data['image_path'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => (string) ($data['status'] ?? 'active'),
                'publish_at' => $data['publish_at'] ?? null,
                'archive_at' => $data['archive_at'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $collectionId = (int) $this->writePdo->lastInsertId();
            $this->upsertTranslations($collectionId, $translations, $data['created_by'] ?? null);

            return $uuid;
        });
    }

    public function update(string $uuid, array $data, array $translations): bool
    {
        return $this->transaction(function () use ($uuid, $data, $translations): bool {
            $collection = $this->fetchOne(
                'SELECT id FROM collections WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $uuid],
                false
            );
            if ($collection === null) {
                return false;
            }

            $sets = ['updated_at = CURRENT_TIMESTAMP(6)'];
            $params = ['uuid' => $uuid];
            $map = [
                'slug' => 'slug',
                'collection_type' => 'collection_type',
                'image_path' => 'image_path',
                'sort_order' => 'sort_order',
                'status' => 'status',
                'publish_at' => 'publish_at',
                'archive_at' => 'archive_at',
                'updated_by' => 'updated_by',
            ];
            foreach ($map as $key => $column) {
                if (array_key_exists($key, $data)) {
                    $sets[] = $column . ' = :' . $key;
                    $params[$key] = $data[$key];
                }
            }

            $stmt = $this->writePdo->prepare(
                'UPDATE collections SET ' . implode(', ', $sets) . '
                 WHERE uuid = :uuid AND deleted_at IS NULL'
            );
            $stmt->execute($params);

            $this->upsertTranslations((int) $collection['id'], $translations, $data['updated_by'] ?? null);

            return $stmt->rowCount() > 0 || $translations !== [];
        });
    }

    public function replaceProducts(string $collectionUuid, array $productUuids): void
    {
        $this->transaction(function () use ($collectionUuid, $productUuids): void {
            $collection = $this->fetchOne(
                'SELECT id FROM collections WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $collectionUuid],
                false
            );
            if ($collection === null) {
                throw new \RuntimeException('Collection not found', 404);
            }

            $collectionId = (int) $collection['id'];
            $this->writePdo->prepare(
                'UPDATE collection_products
                 SET deleted_at = CURRENT_TIMESTAMP(6)
                 WHERE collection_id = :collection_id AND deleted_at IS NULL'
            )->execute(['collection_id' => $collectionId]);

            $sortOrder = 0;
            foreach ($productUuids as $productUuid) {
                if ($productUuid === '') {
                    continue;
                }
                $productId = $this->resolveProductIdByUuid((string) $productUuid);
                $this->writePdo->prepare(
                    'INSERT INTO collection_products (uuid, collection_id, product_id, sort_order)
                     VALUES (:uuid, :collection_id, :product_id, :sort_order)
                     ON DUPLICATE KEY UPDATE
                        sort_order = VALUES(sort_order),
                        deleted_at = NULL,
                        deleted_by = NULL,
                        updated_at = CURRENT_TIMESTAMP(6)'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'collection_id' => $collectionId,
                    'product_id' => $productId,
                    'sort_order' => $sortOrder,
                ]);
                $sortOrder++;
            }
        });
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     */
    private function upsertTranslations(int $collectionId, array $translations, ?int $actorId): void
    {
        foreach ($translations as $localeKey => $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $languageCode = (string) ($translation['language_code'] ?? (is_string($localeKey) ? $localeKey : ''));
            if ($languageCode === '') {
                continue;
            }

            $this->writePdo->prepare(
                'INSERT INTO collection_translations
                 (uuid, collection_id, language_code, name, description, created_by)
                 VALUES (:uuid, :collection_id, :language_code, :name, :description, :created_by)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    updated_by = VALUES(created_by),
                    updated_at = CURRENT_TIMESTAMP(6),
                    deleted_at = NULL'
            )->execute([
                'uuid' => $this->newUuid(),
                'collection_id' => $collectionId,
                'language_code' => $languageCode,
                'name' => (string) ($translation['name'] ?? ''),
                'description' => $translation['description'] ?? null,
                'created_by' => $actorId,
            ]);
        }
    }
}
