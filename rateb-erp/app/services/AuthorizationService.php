<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Permission;
use Rateb\App\Models\Role;

final class AuthorizationService
{
    public function userHasPermission(int $userId, string $permissionSlug): bool
    {
        if ($permissionSlug === '') {
            return true;
        }

        $row = (new Permission())->queryOne(
            'SELECT p.id FROM rateb_permissions p
             JOIN rateb_role_permissions rp ON rp.permission_id = p.id
             JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :uid AND p.slug = :slug LIMIT 1',
            ['uid' => $userId, 'slug' => $permissionSlug]
        );
        return $row !== null;
    }

    /** @return array<int, string> */
    public function userPermissionSlugs(int $userId): array
    {
        $rows = (new Permission())->query(
            'SELECT p.slug FROM rateb_permissions p
             JOIN rateb_role_permissions rp ON rp.permission_id = p.id
             JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :uid',
            ['uid' => $userId]
        );
        return array_values(array_unique(array_column($rows, 'slug')));
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $stmt = (new Role())->query(
            'INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $userId, 'rid' => $roleId]
        );
    }

    /** @return array<int, int> */
    public function getUserRoleIds(int $userId): array
    {
        $rows = (new Role())->query(
            'SELECT role_id FROM rateb_user_roles WHERE user_id = :uid',
            ['uid' => $userId]
        );
        return array_map('intval', array_column($rows, 'role_id'));
    }

    /** @param array<int, int|string> $roleIds */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_user_roles WHERE user_id = :uid')->execute(['uid' => $userId]);
        if ($roleIds === []) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $check = $db->prepare('SELECT id FROM rateb_roles WHERE id IN (' . $placeholders . ')');
        $check->execute($ids);
        $valid = array_map('intval', array_column($check->fetchAll(\PDO::FETCH_ASSOC), 'id'));
        $stmt = $db->prepare('INSERT INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)');
        foreach ($valid as $rid) {
            $stmt->execute(['uid' => $userId, 'rid' => $rid]);
        }
    }

    /** @return array<int, int> */
    public function getRolePermissionIds(int $roleId): array
    {
        $rows = (new Permission())->query(
            'SELECT permission_id FROM rateb_role_permissions WHERE role_id = :rid',
            ['rid' => $roleId]
        );
        return array_map('intval', array_column($rows, 'permission_id'));
    }

    /** @param array<int, int|string> $permissionIds */
    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_role_permissions WHERE role_id = :rid')->execute(['rid' => $roleId]);
        $stmt = $db->prepare('INSERT INTO rateb_role_permissions (role_id, permission_id) VALUES (:rid, :pid)');
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            if ($pid > 0) {
                $stmt->execute(['rid' => $roleId, 'pid' => $pid]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function allPermissionsGrouped(): array
    {
        $rows = (new Permission())->query('SELECT * FROM rateb_permissions ORDER BY module, slug');
        $grouped = [];
        foreach ($rows as $row) {
            $mod = (string) ($row['module'] ?? 'general');
            $grouped[$mod][] = $row;
        }
        return $grouped;
    }
}
