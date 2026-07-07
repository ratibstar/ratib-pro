<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminWriteRepositoryInterface;

final class MysqlRbacAdminWriteRepository extends BaseRepository implements RbacAdminWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'platform_roles';
    }

    public function updateRole(string $roleUuid, array $data, ?int $actorId): bool
    {
        $role = $this->fetchOne(
            'SELECT id, is_system FROM platform_roles WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $roleUuid],
            false
        );
        if ($role === null) {
            throw new \RuntimeException('Role not found', 404);
        }

        $sets = ['updated_by = :updated_by'];
        $params = ['uuid' => $roleUuid, 'updated_by' => $actorId];

        if (array_key_exists('name', $data)) {
            $sets[] = 'name = :name';
            $params['name'] = (string) $data['name'];
        }
        if (array_key_exists('status', $data)) {
            $status = (string) $data['status'];
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new \InvalidArgumentException('status must be active or inactive');
            }
            if ((int) $role['is_system'] === 1 && $status === 'inactive') {
                throw new \InvalidArgumentException('System roles cannot be deactivated');
            }
            $sets[] = 'status = :status';
            $params['status'] = $status;
        }

        if (count($sets) === 1) {
            throw new \InvalidArgumentException('No updatable fields provided');
        }

        $sql = 'UPDATE platform_roles SET ' . implode(', ', $sets) . ' WHERE uuid = :uuid AND deleted_at IS NULL';
        $stmt = $this->writePdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function syncUserRoles(string $userUuid, array $roleUuids, ?int $actorId): void
    {
        $this->transaction(function () use ($userUuid, $roleUuids, $actorId): void {
            $user = $this->fetchOne(
                'SELECT id FROM platform_users WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $userUuid],
                false
            );
            if ($user === null) {
                throw new \RuntimeException('User not found', 404);
            }

            $userId = (int) $user['id'];
            $roleIds = [];
            foreach ($roleUuids as $roleUuid) {
                if (!is_string($roleUuid) || $roleUuid === '') {
                    continue;
                }
                $role = $this->fetchOne(
                    'SELECT id FROM platform_roles WHERE uuid = :uuid AND status = "active" AND deleted_at IS NULL LIMIT 1',
                    ['uuid' => $roleUuid],
                    false
                );
                if ($role === null) {
                    throw new \InvalidArgumentException('Invalid or inactive role: ' . $roleUuid);
                }
                $roleIds[] = (int) $role['id'];
            }

            $this->writePdo->prepare(
                'UPDATE platform_user_roles SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :actor_id
                 WHERE user_id = :user_id AND deleted_at IS NULL'
            )->execute(['user_id' => $userId, 'actor_id' => $actorId]);

            $insert = $this->writePdo->prepare(
                'INSERT INTO platform_user_roles (user_id, role_id, created_by)
                 VALUES (:user_id, :role_id, :created_by)
                 ON DUPLICATE KEY UPDATE deleted_at = NULL, deleted_by = NULL, updated_by = VALUES(created_by)'
            );
            foreach ($roleIds as $roleId) {
                $insert->execute([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'created_by' => $actorId,
                ]);
            }
        });
    }
}
