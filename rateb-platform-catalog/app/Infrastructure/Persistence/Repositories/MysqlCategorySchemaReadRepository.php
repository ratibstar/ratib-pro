<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaReadRepositoryInterface;

final class MysqlCategorySchemaReadRepository extends BaseRepository implements CategorySchemaReadRepositoryInterface
{
    protected function table(): string
    {
        return 'category_attribute_schemas';
    }

    public function findCategoryIdByUuid(string $categoryUuid): ?int
    {
        $row = $this->fetchOne(
            'SELECT id FROM categories WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $categoryUuid]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function listRequiredForCategory(int $categoryId): array
    {
        $resolved = $this->listResolvedSchemaForCategory($categoryId);
        $required = [];
        foreach ($resolved as $row) {
            $isRequired = (bool) ($row['is_required'] ?? false)
                || (string) ($row['inheritance'] ?? '') === 'inherit_required';
            if (!$isRequired) {
                continue;
            }
            $required[] = [
                'attribute_id' => (int) $row['attribute_id'],
                'attribute_uuid' => (string) $row['attribute_uuid'],
                'attribute_code' => (string) $row['attribute_code'],
                'category_id' => (int) $row['category_id'],
            ];
        }

        return $required;
    }

    public function listResolvedSchemaForCategory(int $categoryId): array
    {
        $categoryIds = $this->resolveCategoryAncestryOrdered($categoryId);
        if ($categoryIds === []) {
            return [];
        }

        $depthMap = array_flip($categoryIds);
        $inClause = [];
        $params = [];
        foreach ($categoryIds as $index => $id) {
            $key = 'cid' . $index;
            $inClause[] = ':' . $key;
            $params[$key] = $id;
        }

        $rows = $this->fetchAll(
            'SELECT cas.category_id, cas.attribute_id, cas.is_required, cas.inheritance, cas.sort_order,
                    a.uuid AS attribute_uuid, a.code AS attribute_code
             FROM category_attribute_schemas cas
             INNER JOIN attributes a ON a.id = cas.attribute_id AND a.deleted_at IS NULL
             WHERE cas.category_id IN (' . implode(',', $inClause) . ')
               AND cas.deleted_at IS NULL
             ORDER BY cas.sort_order ASC, cas.id ASC',
            $params
        );

        usort($rows, static function (array $left, array $right) use ($depthMap): int {
            $leftDepth = $depthMap[(int) $left['category_id']] ?? 0;
            $rightDepth = $depthMap[(int) $right['category_id']] ?? 0;

            return $leftDepth <=> $rightDepth;
        });

        $merged = [];
        foreach ($rows as $row) {
            $attributeId = (int) $row['attribute_id'];
            $merged[$attributeId] = $row;
        }

        return array_values($merged);
    }

    /**
     * Root-to-leaf category ids for inheritance (child overrides parent).
     *
     * @return list<int>
     */
    private function resolveCategoryAncestryOrdered(int $categoryId): array
    {
        $row = $this->fetchOne(
            'SELECT id, path FROM categories WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $categoryId]
        );
        if ($row === null) {
            return [];
        }

        $ids = [];
        $path = trim((string) ($row['path'] ?? ''), '/');
        if ($path !== '') {
            foreach (explode('/', $path) as $segment) {
                if ($segment !== '' && ctype_digit($segment)) {
                    $ids[] = (int) $segment;
                }
            }
        }
        $ids[] = (int) $row['id'];

        return array_values(array_unique($ids));
    }
}
