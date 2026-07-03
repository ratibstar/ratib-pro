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

    public function userHasAnyRole(int $userId): bool
    {
        return $this->getUserRoleIds($userId) !== [];
    }

    /** Company portal: explicit RBAC only (permission via assigned roles). */
    public function companyUserCan(int $userId, string $permissionSlug, string $module = ''): bool
    {
        unset($module);
        if ($permissionSlug === '') {
            return true;
        }
        return $this->userHasPermission($userId, $permissionSlug);
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
        $hidden = [];
        $cfgFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        if (is_file($cfgFile)) {
            $cfg = require $cfgFile;
            $hidden = is_array($cfg['matrix_hidden_slugs'] ?? null) ? $cfg['matrix_hidden_slugs'] : [];
        }
        $grouped = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && in_array($slug, $hidden, true)) {
                continue;
            }
            $mod = (string) ($row['module'] ?? 'general');
            if (self::isAgencyPermissionMatrixContext()) {
                $platformMods = is_array($cfg['platform_modules'] ?? null) ? $cfg['platform_modules'] : [];
                if (in_array($mod, $platformMods, true)) {
                    continue;
                }
                $excluded = is_array($cfg['company_role_excluded_slugs'] ?? null) ? $cfg['company_role_excluded_slugs'] : [];
                $dedicatedExtra = is_array($cfg['dedicated_company_admin_slugs'] ?? null) ? $cfg['dedicated_company_admin_slugs'] : [];
                if (in_array($slug, $excluded, true) && !in_array($slug, $dedicatedExtra, true)) {
                    continue;
                }
            }
            $grouped[$mod][] = $row;
        }

        $accountingOrder = [
            'accounting.view' => 1,
            'accounting.manage' => 2,
            'accounting.approve' => 3,
            'accounting.post' => 4,
        ];
        foreach ($grouped as $mod => &$perms) {
            if ($mod === 'accounting') {
                usort($perms, static function (array $a, array $b) use ($accountingOrder): int {
                    $sa = (string) ($a['slug'] ?? '');
                    $sb = (string) ($b['slug'] ?? '');
                    return ($accountingOrder[$sa] ?? 99) <=> ($accountingOrder[$sb] ?? 99);
                });
            }
        }
        unset($perms);

        $moduleOrder = [
            'dashboard' => 0,
            'accounting' => 1,
            'procurement' => 2,
            'inventory' => 3,
            'suppliers' => 4,
            'assets' => 5,
            'contracts' => 6,
            'tenders' => 7,
            'reports' => 8,
            'medical_devices' => 9,
            'hr' => 10,
            'branches' => 11,
            'documents' => 12,
            'workflows' => 13,
            'notifications' => 14,
            'cms' => 15,
        ];
        uksort($grouped, static function (string $a, string $b) use ($moduleOrder): int {
            return ($moduleOrder[$a] ?? 99) <=> ($moduleOrder[$b] ?? 99);
        });

        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    public function allRoles(): array
    {
        $rows = (new Role())->query('SELECT * FROM rateb_roles ORDER BY name, id');
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $unique[] = $row;
        }
        return $unique;
    }

    public function dedupeDuplicateRoles(): int
    {
        $db = \Rateb\App\Core\Database::connection();

        $db->exec(
            'INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
             SELECT keeper.id, rp.permission_id
             FROM rateb_roles dup
             INNER JOIN rateb_roles keeper ON keeper.slug = dup.slug AND keeper.id < dup.id
             INNER JOIN rateb_role_permissions rp ON rp.role_id = dup.id'
        );

        $db->exec(
            'INSERT IGNORE INTO rateb_user_roles (user_id, role_id)
             SELECT ur.user_id, keeper.id
             FROM rateb_roles dup
             INNER JOIN rateb_roles keeper ON keeper.slug = dup.slug AND keeper.id < dup.id
             INNER JOIN rateb_user_roles ur ON ur.role_id = dup.id'
        );

        return $db->exec(
            'DELETE r1 FROM rateb_roles r1
             INNER JOIN rateb_roles r2 ON r1.slug = r2.slug AND r1.id > r2.id'
        );
    }

    /** @return array<int, array<int, int>> roleId => permission ids */
    public function rolePermissionMatrix(): array
    {
        $matrix = [];
        foreach ($this->allRoles() as $role) {
            $matrix[(int) $role['id']] = $this->getRolePermissionIds((int) $role['id']);
        }
        return $matrix;
    }

    /** @param array<int|string, array<int|string>> $matrix roleId => permission ids */
    public function syncMatrixFromPost(array $matrix): void
    {
        foreach ($this->allRoles() as $role) {
            $roleId = (int) $role['id'];
            $permIds = array_map('intval', (array) ($matrix[$roleId] ?? $matrix[(string) $roleId] ?? []));
            $this->syncRolePermissions($roleId, $permIds);
        }
    }

    public function getUserRoleNames(int $userId): string
    {
        $rows = (new Role())->query(
            'SELECT r.name FROM rateb_roles r
             JOIN rateb_user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :uid ORDER BY r.name',
            ['uid' => $userId]
        );
        return implode(', ', array_column($rows, 'name'));
    }

    public function getRolePermissionCount(int $roleId): int
    {
        $row = (new Permission())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_role_permissions WHERE role_id = :rid',
            ['rid' => $roleId]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array{slug:string,name:string,description:string,is_system:bool,permissions:list<string>|null}> */
    public static function suggestedRoleDefinitions(): array
    {
        return [
            ['slug' => 'super-admin', 'name' => 'Super Admin', 'description' => 'Platform super administrator', 'is_system' => true, 'permissions' => null],
            ['slug' => 'access-manager', 'name' => 'Access Manager', 'description' => 'Users and roles management', 'is_system' => true, 'permissions' => ['access.manage', 'users.manage', 'roles.manage', 'permissions.manage', 'dashboard.view']],
            ['slug' => 'accountant', 'name' => 'Accountant', 'description' => 'Accounting and reports access', 'is_system' => true, 'permissions' => ['accounting.view', 'accounting.manage', 'accounting.post', 'reports.view', 'dashboard.view']],
            ['slug' => 'accounting-approver', 'name' => 'Accounting Approver', 'description' => 'Approve journal entries and cash vouchers', 'is_system' => true, 'permissions' => ['dashboard.view', 'accounting.approve']],
            ['slug' => 'company-full-access', 'name' => 'Company Full Access', 'description' => 'Default ERP access for company portal users', 'is_system' => true, 'permissions' => ['__company_full_access__']],
            ['slug' => 'hq_admin', 'name' => 'HQ Admin', 'description' => 'Head office — all branches', 'is_system' => true, 'permissions' => ['branches.access_all', 'branch.dashboard.view', 'branch.dashboard.compare', 'branch.reports.view', 'branch.transfers.view', 'branch.transfers.manage', 'branches.view', 'branches.manage', 'dashboard.view']],
            ['slug' => 'hq_manager', 'name' => 'HQ Manager', 'description' => 'Head office manager — all branches read/compare', 'is_system' => true, 'permissions' => ['branches.access_all', 'branch.dashboard.view', 'branch.dashboard.compare', 'branch.reports.view', 'branch.transfers.view', 'dashboard.view']],
            ['slug' => 'branch_manager', 'name' => 'Branch Manager', 'description' => 'Single-branch manager', 'is_system' => true, 'permissions' => ['branch.dashboard.view', 'branch.reports.view', 'branch.transfers.view', 'branch.transfers.manage', 'branches.view', 'dashboard.view']],
            ['slug' => 'branch_user', 'name' => 'Branch User', 'description' => 'Single-branch operational user', 'is_system' => true, 'permissions' => ['branch.dashboard.view', 'branch.reports.view', 'dashboard.view']],
            ['slug' => 'procurement-manager', 'name' => 'Procurement Manager', 'description' => 'Manage purchase requests, orders, and RFQ', 'is_system' => true, 'permissions' => ['procurement.manage', 'dashboard.view', 'reports.view']],
            ['slug' => 'inventory-manager', 'name' => 'Inventory Manager', 'description' => 'Manage inventory, warehouses, and stock', 'is_system' => true, 'permissions' => ['inventory.manage', 'dashboard.view', 'reports.view']],
            ['slug' => 'hr-manager', 'name' => 'HR Manager', 'description' => 'Manage employees, attendance, and payroll', 'is_system' => true, 'permissions' => ['hr.view', 'hr.manage', 'dashboard.view', 'reports.view']],
        ];
    }

    public function ensureSuggestedRoles(): void
    {
        $roleModel = new Role();
        foreach (self::suggestedRoleDefinitions() as $def) {
            $slug = (string) $def['slug'];
            $existing = $roleModel->queryOne(
                'SELECT id FROM rateb_roles WHERE slug = :slug LIMIT 1',
                ['slug' => $slug]
            );
            $roleId = (int) ($existing['id'] ?? 0);
            if ($roleId < 1) {
                $roleId = $roleModel->create([
                    'company_id' => null,
                    'name' => (string) $def['name'],
                    'slug' => $slug,
                    'description' => (string) $def['description'],
                    'is_system' => !empty($def['is_system']) ? 1 : 0,
                ]);
            }
            if ($roleId < 1 || $def['permissions'] === null) {
                continue;
            }
            $permSlugs = $def['permissions'];
            if ($permSlugs === ['__company_full_access__']) {
                $this->syncCompanyFullAccessPermissions($roleId);
                continue;
            }
            $this->grantRolePermissionsBySlugs($roleId, $permSlugs);
        }
    }

    /** @param list<string> $slugs */
    private function grantRolePermissionsBySlugs(int $roleId, array $slugs): void
    {
        if ($roleId < 1 || $slugs === []) {
            return;
        }
        $db = \Rateb\App\Core\Database::connection();
        foreach ($slugs as $slug) {
            $pid = (new Permission())->queryOne(
                'SELECT id FROM rateb_permissions WHERE slug = :slug LIMIT 1',
                ['slug' => $slug]
            );
            if (!$pid) {
                continue;
            }
            $db->prepare(
                'INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id) VALUES (:rid, :pid)'
            )->execute(['rid' => $roleId, 'pid' => (int) $pid['id']]);
        }
    }

    private function syncCompanyFullAccessPermissions(int $roleId): void
    {
        if ($roleId < 1) {
            return;
        }
        $configFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        $config = is_file($configFile) ? require $configFile : [];
        $excluded = (array) ($config['company_role_excluded_slugs'] ?? []);
        if (self::isAgencyPermissionMatrixContext()) {
            $extra = (array) ($config['dedicated_company_admin_slugs'] ?? []);
            $excluded = array_values(array_diff($excluded, $extra));
        }
        $rows = (new Permission())->query('SELECT id, slug FROM rateb_permissions');
        $ids = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || in_array($slug, $excluded, true)) {
                continue;
            }
            $ids[] = (int) ($row['id'] ?? 0);
        }
        $this->syncRolePermissions($roleId, array_values(array_filter($ids)));
    }

    public function refreshDedicatedCompanyAccessPermissions(): void
    {
        if (!self::isAgencyPermissionMatrixContext()) {
            return;
        }
        $this->ensureSuggestedRoles();
        $role = (new Role())->queryOne(
            "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
        );
        if (!$role) {
            return;
        }
        $this->syncCompanyFullAccessPermissions((int) $role['id']);
    }

    /** Ensure agency company admin has company-full-access role (idempotent). */
    public function ensureAgencyCompanyAdminRole(int $userId): void
    {
        if ($userId < 1 || !self::isAgencyPermissionMatrixContext()) {
            return;
        }
        $user = (new \Rateb\App\Models\User())->find($userId);
        if (!$user || !empty($user['is_super_admin'])) {
            return;
        }
        $eligible = (int) ($user['company_id'] ?? 0) > 0;
        if (!$eligible) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $name = strtolower(trim((string) ($user['name'] ?? '')));
            $eligible = $email === 'admin@local' || $name === 'admin' || str_starts_with($email, 'admin+');
        }
        if (!$eligible) {
            return;
        }
        $role = (new Role())->queryOne(
            "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
        );
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];
        $existing = (new Role())->queryOne(
            'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
            ['uid' => $userId, 'rid' => $roleId]
        );
        if ($existing === null) {
            $db = \Rateb\App\Core\Database::connection();
            $db->prepare(
                'INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)'
            )->execute(['uid' => $userId, 'rid' => $roleId]);
        }
    }

    private static function isAgencyPermissionMatrixContext(): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        return function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment();
    }
}
