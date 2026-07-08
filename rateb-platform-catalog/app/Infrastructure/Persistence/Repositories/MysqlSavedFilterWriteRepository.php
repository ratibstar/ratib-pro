<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterWriteRepositoryInterface;

final class MysqlSavedFilterWriteRepository extends BaseRepository implements SavedFilterWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'saved_filters';
    }

    public function create(
        int $userId,
        string $name,
        string $entityType,
        array $filter,
        ?array $sort,
        bool $isDefault,
        bool $isShared
    ): string {
        return $this->transaction(function () use ($userId, $name, $entityType, $filter, $sort, $isDefault, $isShared): string {
            if ($isDefault) {
                $this->clearDefaultForUser($userId, $entityType);
            }

            $uuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO saved_filters
                 (uuid, platform_user_id, name, entity_type, filter_json, sort_json, is_default, is_shared, created_by)
                 VALUES (:uuid, :platform_user_id, :name, :entity_type, :filter_json, :sort_json, :is_default, :is_shared, :created_by)'
            )->execute([
                'uuid' => $uuid,
                'platform_user_id' => $userId,
                'name' => $name,
                'entity_type' => $entityType,
                'filter_json' => json_encode($filter, JSON_UNESCAPED_UNICODE) ?: '{}',
                'sort_json' => $sort === null ? null : (json_encode($sort, JSON_UNESCAPED_UNICODE) ?: '{}'),
                'is_default' => (int) $isDefault,
                'is_shared' => (int) $isShared,
                'created_by' => $userId,
            ]);

            return $uuid;
        });
    }

    public function update(
        string $uuid,
        int $userId,
        string $name,
        array $filter,
        ?array $sort,
        bool $isDefault,
        bool $isShared
    ): bool {
        return $this->transaction(function () use ($uuid, $userId, $name, $filter, $sort, $isDefault, $isShared): bool {
            $existing = $this->fetchOne(
                'SELECT entity_type FROM saved_filters
                 WHERE uuid = :uuid AND platform_user_id = :user_id AND deleted_at IS NULL
                 LIMIT 1 FOR UPDATE',
                ['uuid' => $uuid, 'user_id' => $userId],
                false
            );
            if ($existing === null) {
                return false;
            }

            if ($isDefault) {
                $this->clearDefaultForUser($userId, (string) $existing['entity_type'], $uuid);
            }

            $stmt = $this->writePdo->prepare(
                'UPDATE saved_filters
                 SET name = :name,
                     filter_json = :filter_json,
                     sort_json = :sort_json,
                     is_default = :is_default,
                     is_shared = :is_shared,
                     updated_by = :updated_by,
                     updated_at = CURRENT_TIMESTAMP(6)
                 WHERE uuid = :uuid AND platform_user_id = :user_id AND deleted_at IS NULL'
            );
            $stmt->execute([
                'uuid' => $uuid,
                'user_id' => $userId,
                'name' => $name,
                'filter_json' => json_encode($filter, JSON_UNESCAPED_UNICODE) ?: '{}',
                'sort_json' => $sort === null ? null : (json_encode($sort, JSON_UNESCAPED_UNICODE) ?: '{}'),
                'is_default' => (int) $isDefault,
                'is_shared' => (int) $isShared,
                'updated_by' => $userId,
            ]);

            return $stmt->rowCount() > 0;
        });
    }

    public function delete(string $uuid, int $userId): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE saved_filters
             SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND platform_user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'user_id' => $userId,
            'deleted_by' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function clearDefaultForUser(int $userId, string $entityType, ?string $excludeUuid = null): void
    {
        $sql = 'UPDATE saved_filters
                SET is_default = 0, updated_at = CURRENT_TIMESTAMP(6)
                WHERE platform_user_id = :user_id AND entity_type = :entity_type
                  AND is_default = 1 AND deleted_at IS NULL';
        $params = ['user_id' => $userId, 'entity_type' => $entityType];
        if ($excludeUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeUuid;
        }

        $this->writePdo->prepare($sql)->execute($params);
    }
}
