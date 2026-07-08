<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterReadRepositoryInterface;

final class MysqlSavedFilterReadRepository extends BaseRepository implements SavedFilterReadRepositoryInterface
{
    protected function table(): string
    {
        return 'saved_filters';
    }

    public function listForUser(int $userId, ?string $entityType): array
    {
        $where = ['platform_user_id = :user_id', 'deleted_at IS NULL'];
        $params = ['user_id' => $userId];
        if ($entityType !== null && $entityType !== '') {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        $rows = $this->fetchAll(
            'SELECT uuid, name, entity_type, filter_json, sort_json, is_default, is_shared,
                    created_at, updated_at
             FROM saved_filters
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY is_default DESC, name ASC, id ASC',
            $params
        );

        return array_map(fn (array $row): array => $this->decodeJsonFields($row), $rows);
    }

    public function findByUuid(string $uuid, int $userId): ?array
    {
        $row = $this->fetchOne(
            'SELECT uuid, name, entity_type, filter_json, sort_json, is_default, is_shared,
                    created_at, updated_at
             FROM saved_filters
             WHERE uuid = :uuid AND platform_user_id = :user_id AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid, 'user_id' => $userId]
        );
        if ($row === null) {
            return null;
        }

        return $this->decodeJsonFields($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeJsonFields(array $row): array
    {
        $filter = json_decode((string) ($row['filter_json'] ?? '{}'), true);
        $row['filter_json'] = is_array($filter) ? $filter : [];

        if ($row['sort_json'] !== null) {
            $sort = json_decode((string) $row['sort_json'], true);
            $row['sort_json'] = is_array($sort) ? $sort : null;
        }

        return $row;
    }
}
