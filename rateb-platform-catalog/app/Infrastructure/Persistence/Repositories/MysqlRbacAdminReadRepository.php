<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;

final class MysqlRbacAdminReadRepository extends BaseRepository implements RbacAdminReadRepositoryInterface
{
    protected function table(): string
    {
        return 'platform_roles';
    }

    public function listRoles(): array
    {
        return $this->fetchAll(
            'SELECT uuid, code, name, is_system, status, created_at, updated_at
             FROM platform_roles
             WHERE deleted_at IS NULL
             ORDER BY name ASC'
        );
    }

    public function findRoleByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT uuid, code, name, is_system, status, created_at, updated_at
             FROM platform_roles
             WHERE uuid = :uuid AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function findUserByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT id, uuid, email, display_name, status
             FROM platform_users
             WHERE uuid = :uuid AND deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function listRolesForUserUuid(string $userUuid): array
    {
        return $this->fetchAll(
            'SELECT r.uuid, r.code, r.name, r.is_system, r.status
             FROM platform_user_roles ur
             INNER JOIN platform_users u ON u.id = ur.user_id AND u.deleted_at IS NULL
             INNER JOIN platform_roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
             WHERE u.uuid = :user_uuid AND ur.deleted_at IS NULL
             ORDER BY r.name ASC',
            ['user_uuid' => $userUuid]
        );
    }
}
