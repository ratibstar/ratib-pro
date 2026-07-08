<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

final class MysqlRbacReadRepository extends BaseRepository implements RbacReadRepositoryInterface
{
    protected function table(): string
    {
        return 'platform_users';
    }

    public function listPermissionSlugsForUser(int $userId): array
    {
        if (!$this->userIsActive($userId)) {
            return [];
        }

        $rolePerms = $this->fetchAll(
            'SELECT p.slug
             FROM platform_user_roles ur
             INNER JOIN platform_roles r ON r.id = ur.role_id AND r.status = "active" AND r.deleted_at IS NULL
             INNER JOIN platform_role_permissions rp ON rp.role_id = r.id AND rp.deleted_at IS NULL
             INNER JOIN platform_permissions p ON p.id = rp.permission_id AND p.deleted_at IS NULL
             WHERE ur.user_id = :user_id AND ur.deleted_at IS NULL',
            ['user_id' => $userId]
        );

        $overrides = $this->fetchAll(
            'SELECT p.slug, up.is_granted
             FROM platform_user_permissions up
             INNER JOIN platform_permissions p ON p.id = up.permission_id AND p.deleted_at IS NULL
             WHERE up.user_id = :user_id AND up.deleted_at IS NULL',
            ['user_id' => $userId]
        );

        $granted = [];
        foreach ($rolePerms as $row) {
            $granted[(string) $row['slug']] = true;
        }
        foreach ($overrides as $row) {
            $slug = (string) $row['slug'];
            if ((int) ($row['is_granted'] ?? 0) === 1) {
                $granted[$slug] = true;
            } else {
                unset($granted[$slug]);
            }
        }

        return array_keys($granted);
    }

    public function userIsActive(int $userId): bool
    {
        $row = $this->fetchOne(
            'SELECT id FROM platform_users WHERE id = :id AND status = "active" AND deleted_at IS NULL LIMIT 1',
            ['id' => $userId]
        );

        return $row !== null;
    }

    public function findActiveUserIdByUuid(string $uuid): ?int
    {
        $row = $this->fetchOne(
            'SELECT id FROM platform_users WHERE uuid = :uuid AND status = "active" AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function findActiveUserIdByEmail(string $email): ?int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $row = $this->fetchOne(
            'SELECT id FROM platform_users WHERE email = :email AND status = "active" AND deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );

        return $row !== null ? (int) $row['id'] : null;
    }
}
