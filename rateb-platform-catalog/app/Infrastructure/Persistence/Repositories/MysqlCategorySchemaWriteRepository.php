<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaWriteRepositoryInterface;

final class MysqlCategorySchemaWriteRepository extends BaseRepository implements CategorySchemaWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'category_attribute_schemas';
    }

    public function replaceForCategory(int $categoryId, array $items, ?int $actorId = null): void
    {
        $this->transaction(function () use ($categoryId, $items, $actorId): void {
            $keptAttributeIds = [];
            $sort = 0;
            foreach ($items as $item) {
                $attributeId = $this->resolveRequiredId('attributes', (string) $item['attribute_uuid']);
                $keptAttributeIds[] = $attributeId;
                $existing = $this->fetchOne(
                    'SELECT id FROM category_attribute_schemas
                     WHERE category_id = :category_id AND attribute_id = :attribute_id
                     LIMIT 1',
                    ['category_id' => $categoryId, 'attribute_id' => $attributeId],
                    false
                );

                if ($existing !== null) {
                    $this->writePdo->prepare(
                        'UPDATE category_attribute_schemas
                         SET is_required = :is_required, sort_order = :sort_order, inheritance = :inheritance,
                             deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by, updated_at = CURRENT_TIMESTAMP(6)
                         WHERE id = :id'
                    )->execute([
                        'id' => (int) $existing['id'],
                        'is_required' => (int) ($item['is_required'] ?? false),
                        'sort_order' => (int) ($item['sort_order'] ?? $sort),
                        'inheritance' => (string) ($item['inheritance'] ?? 'none'),
                        'updated_by' => $actorId,
                    ]);
                } else {
                    $this->writePdo->prepare(
                        'INSERT INTO category_attribute_schemas
                         (uuid, category_id, attribute_id, is_required, sort_order, inheritance, created_by)
                         VALUES (:uuid, :category_id, :attribute_id, :is_required, :sort_order, :inheritance, :created_by)'
                    )->execute([
                        'uuid' => $this->newUuid(),
                        'category_id' => $categoryId,
                        'attribute_id' => $attributeId,
                        'is_required' => (int) ($item['is_required'] ?? false),
                        'sort_order' => (int) ($item['sort_order'] ?? $sort),
                        'inheritance' => (string) ($item['inheritance'] ?? 'none'),
                        'created_by' => $actorId,
                    ]);
                }
                $sort++;
            }

            if ($keptAttributeIds === []) {
                $this->writePdo->prepare(
                    'UPDATE category_attribute_schemas
                     SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                     WHERE category_id = :category_id AND deleted_at IS NULL'
                )->execute(['category_id' => $categoryId, 'deleted_by' => $actorId]);

                return;
            }

            $inClause = [];
            $params = ['category_id' => $categoryId, 'deleted_by' => $actorId];
            foreach ($keptAttributeIds as $index => $attributeId) {
                $key = 'aid' . $index;
                $inClause[] = ':' . $key;
                $params[$key] = $attributeId;
            }

            $this->writePdo->prepare(
                'UPDATE category_attribute_schemas
                 SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE category_id = :category_id AND deleted_at IS NULL
                   AND attribute_id NOT IN (' . implode(',', $inClause) . ')'
            )->execute($params);
        });
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
}
