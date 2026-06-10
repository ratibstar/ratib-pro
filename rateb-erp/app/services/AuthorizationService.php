<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Permission;
use Rateb\App\Models\Role;

final class AuthorizationService
{
    public function userHasPermission(int $userId, string $permissionSlug): bool
    {
        $row = (new Permission())->queryOne(
            'SELECT p.id FROM rateb_permissions p
             JOIN rateb_role_permissions rp ON rp.permission_id = p.id
             JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :uid AND p.slug = :slug LIMIT 1',
            ['uid' => $userId, 'slug' => $permissionSlug]
        );
        return $row !== null;
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $stmt = (new Role())->query(
            'INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $userId, 'rid' => $roleId]
        );
    }
}
