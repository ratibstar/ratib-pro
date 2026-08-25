<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Permission;
use Rateb\App\Models\Role;
use Rateb\App\Models\User;

final class AuthorizationService
{
    /** @return array<string, mixed> */
    private static function permissionsConfig(): array
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }
        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        $cfg = is_file($file) ? require $file : [];

        return is_array($cfg) ? $cfg : [];
    }

    /** @return list<string> */
    public static function platformRoleSlugs(): array
    {
        $slugs = self::permissionsConfig()['platform_role_slugs'] ?? [];

        return is_array($slugs) ? array_values(array_filter(array_map('strval', $slugs))) : [];
    }

    /** @return list<string> */
    public static function tenantRoleSlugs(): array
    {
        $slugs = self::permissionsConfig()['tenant_role_slugs'] ?? [];

        return is_array($slugs) ? array_values(array_filter(array_map('strval', $slugs))) : [];
    }

    public function userHasPermission(int $userId, string $permissionSlug): bool
    {
        if ($permissionSlug === '') {
            return true;
        }

        // Platform super admin bypasses company-scoped RBAC rows.
        if ($this->userIsSuperAdmin($userId)) {
            return true;
        }

        $params = ['uid' => $userId, 'slug' => $permissionSlug];
        $sql = 'SELECT p.id FROM rateb_permissions p
             JOIN rateb_role_permissions rp ON rp.permission_id = p.id
             JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
             JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND p.slug = :slug';
        $scope = $this->roleCompanyScopeSql($userId, $params);
        if ($scope === '') {
            return false;
        }
        $sql .= $scope . ' LIMIT 1';

        $row = (new Permission())->queryOne($sql, $params);

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
        $params = ['uid' => $userId];
        $sql = 'SELECT p.slug FROM rateb_permissions p
             JOIN rateb_role_permissions rp ON rp.permission_id = p.id
             JOIN rateb_user_roles ur ON ur.role_id = rp.role_id
             JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid';
        $scope = $this->roleCompanyScopeSql($userId, $params);
        if ($scope === '') {
            return [];
        }
        $sql .= $scope;

        $rows = (new Permission())->query($sql, $params);

        return array_values(array_unique(array_column($rows, 'slug')));
    }

    /** @param array<string, int|string> $params */
    private function roleCompanyScopeSql(int $userId, array &$params): string
    {
        if ($this->userIsSuperAdmin($userId)) {
            return '';
        }
        $companyId = $this->userCompanyId($userId);
        if ($companyId < 1) {
            // Platform staff: roles with company_id IS NULL only.
            return ' AND r.company_id IS NULL';
        }
        $params['role_cid'] = $companyId;

        return ' AND r.company_id = :role_cid';
    }

    /** Non-SA platform operator (no tenant company) with at least one global role. */
    public function userIsPlatformStaff(int $userId): bool
    {
        if ($userId < 1 || $this->userIsSuperAdmin($userId)) {
            return false;
        }
        if ($this->userCompanyId($userId) > 0) {
            return false;
        }
        $row = (new Role())->queryOne(
            'SELECT 1 AS ok FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND r.company_id IS NULL
             LIMIT 1',
            ['uid' => $userId]
        );

        return $row !== null;
    }

    private function userIsSuperAdmin(int $userId): bool
    {
        static $cache = [];
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        $row = (new User())->find($userId);
        $cache[$userId] = !empty($row['is_super_admin']);

        return $cache[$userId];
    }

    private function userCompanyId(int $userId): int
    {
        static $cache = [];
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }
        $row = (new User())->find($userId);
        $cache[$userId] = (int) ($row['company_id'] ?? 0);

        return $cache[$userId];
    }

    public function assignRole(int $userId, int $roleId): void
    {
        if (!$this->roleAssignableToUser($userId, $roleId)) {
            return;
        }
        (new Role())->query(
            'INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)',
            ['uid' => $userId, 'rid' => $roleId]
        );
    }

    /** @return array<int, int> */
    public function getUserRoleIds(int $userId): array
    {
        $params = ['uid' => $userId];
        $sql = 'SELECT ur.role_id FROM rateb_user_roles ur
             JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid';
        $scope = $this->roleCompanyScopeSql($userId, $params);
        if ($scope === '' && !$this->userIsSuperAdmin($userId)) {
            return [];
        }
        $sql .= $scope;
        $rows = (new Role())->query($sql, $params);

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
        $stmt = $db->prepare('INSERT INTO rateb_user_roles (user_id, role_id) VALUES (:uid, :rid)');
        foreach ($ids as $rid) {
            if ($this->roleAssignableToUser($userId, $rid)) {
                $stmt->execute(['uid' => $userId, 'rid' => $rid]);
            }
        }
    }

    public function roleAssignableToUser(int $userId, int $roleId): bool
    {
        if ($roleId < 1) {
            return false;
        }
        if ($this->userIsSuperAdmin($userId)) {
            return (new Role())->find($roleId) !== null;
        }
        $role = (new Role())->find($roleId);
        if (!$role) {
            return false;
        }
        $slug = (string) ($role['slug'] ?? '');
        $companyId = $this->userCompanyId($userId);
        $isPlatformRole = $slug !== '' && in_array($slug, self::platformRoleSlugs(), true);

        // Platform staff (no tenant): only global platform roles — never the full SA slug.
        if ($companyId < 1) {
            return $isPlatformRole
                && $slug !== 'super-admin'
                && (int) ($role['company_id'] ?? 0) < 1;
        }

        // Company users cannot receive platform-scoped roles.
        if ($isPlatformRole) {
            return false;
        }

        return (int) ($role['company_id'] ?? 0) === $companyId;
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
        if (self::isAgencyPermissionMatrixContext()) {
            $this->ensureTenantSelfServicePermissionRows();
        }
        $rows = (new Permission())->query('SELECT * FROM rateb_permissions ORDER BY module, slug');
        $cfg = self::permissionsConfig();
        $hidden = is_array($cfg['matrix_hidden_slugs'] ?? null) ? $cfg['matrix_hidden_slugs'] : [];
        $agencyMatrix = self::isAgencyPermissionMatrixContext();
        if ($agencyMatrix) {
            $agencyHidden = is_array($cfg['agency_matrix_hidden_slugs'] ?? null) ? $cfg['agency_matrix_hidden_slugs'] : [];
            $hidden = array_values(array_unique(array_merge($hidden, $agencyHidden)));
        }
        $grouped = [];
        $platformMods = is_array($cfg['platform_modules'] ?? null) ? $cfg['platform_modules'] : [];
        $excluded = is_array($cfg['company_role_excluded_slugs'] ?? null) ? $cfg['company_role_excluded_slugs'] : [];
        $dedicatedExtra = is_array($cfg['dedicated_company_admin_slugs'] ?? null) ? $cfg['dedicated_company_admin_slugs'] : [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && in_array($slug, $hidden, true)) {
                continue;
            }
            $mod = (string) ($row['module'] ?? 'general');
            if ($agencyMatrix) {
                // Agency / dedicated tenants may assign access.manage + settings.manage
                // (users/roles/tickets/templates) even though some modules are platform-tagged.
                $allowTenantSelfService = $slug !== '' && in_array($slug, $dedicatedExtra, true);
                if (in_array($mod, $platformMods, true) && !$allowTenantSelfService) {
                    continue;
                }
                if (in_array($slug, $excluded, true) && !$allowTenantSelfService) {
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
            'access' => 1,
            'settings' => 2,
            'notifications' => 3,
            'accounting' => 4,
            'procurement' => 5,
            'inventory' => 6,
            'suppliers' => 7,
            'assets' => 8,
            'contracts' => 9,
            'tenders' => 10,
            'reports' => 11,
            'medical_devices' => 12,
            'hr' => 13,
            'branches' => 14,
            'documents' => 15,
            'workflows' => 16,
            'cms' => 17,
        ];
        uksort($grouped, static function (string $a, string $b) use ($moduleOrder): int {
            return ($moduleOrder[$a] ?? 99) <=> ($moduleOrder[$b] ?? 99);
        });

        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    public function allRoles(?int $companyId = null): array
    {
        if ($companyId === null) {
            $companyId = self::resolveMatrixCompanyId();
        }
        if ($companyId > 0) {
            return (new Role())->query(
                'SELECT * FROM rateb_roles WHERE company_id = :cid ORDER BY name, id',
                ['cid' => $companyId]
            );
        }

        $platform = self::platformRoleSlugs();
        if ($platform === []) {
            return (new Role())->query(
                'SELECT * FROM rateb_roles WHERE company_id IS NULL ORDER BY name, id'
            );
        }
        $placeholders = implode(',', array_fill(0, count($platform), '?'));

        return (new Role())->query(
            'SELECT * FROM rateb_roles WHERE company_id IS NULL AND slug IN (' . $placeholders . ') ORDER BY name, id',
            $platform
        );
    }

    public static function resolveMatrixCompanyId(): int
    {
        if (function_exists('rateb_company_access_routes_enabled') && rateb_company_access_routes_enabled()) {
            if (function_exists('rateb_resolve_ops_company_id')) {
                $id = rateb_resolve_ops_company_id();
                if ($id > 0) {
                    return $id;
                }
            }
            $sessionCid = (int) ($_SESSION['rateb_company_id'] ?? 0);
            if ($sessionCid > 0) {
                return $sessionCid;
            }
        }

        return 0;
    }

    /**
     * Explicit RBAC UI scope for platform SA: platform (global staff roles) vs company (tenant clones).
     * Non-SA always company. Default for SA is platform so ops company picker does not mix scopes.
     *
     * @return array{scope:string,company_id:int}
     */
    public static function resolveRbacUiScope(?string $scopeRaw = null): array
    {
        $isSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
        $opsId = 0;
        if (function_exists('rateb_resolve_ops_company_id')) {
            $opsId = (int) rateb_resolve_ops_company_id();
        }
        if ($opsId < 1) {
            $opsId = (int) ($_SESSION['rateb_company_id'] ?? 0);
        }

        $raw = strtolower(trim((string) ($scopeRaw ?? ($_GET['scope'] ?? $_POST['scope'] ?? ''))));
        if (!in_array($raw, ['platform', 'company'], true)) {
            $raw = '';
        }

        if (!$isSa) {
            return [
                'scope' => 'company',
                'company_id' => $opsId > 0 ? $opsId : self::resolveMatrixCompanyId(),
            ];
        }

        if ($raw === 'company') {
            if ($opsId < 1) {
                return ['scope' => 'platform', 'company_id' => 0];
            }

            return ['scope' => 'company', 'company_id' => $opsId];
        }

        // SA default + scope=platform: ignore ops picker for role/matrix lists.
        return ['scope' => 'platform', 'company_id' => 0];
    }

    /** @return array<string, mixed>|null */
    public function findRoleBySlug(string $slug, ?int $companyId = null): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        if ($companyId !== null && $companyId > 0 && !in_array($slug, self::platformRoleSlugs(), true)) {
            return (new Role())->queryOne(
                'SELECT * FROM rateb_roles WHERE slug = :slug AND company_id = :cid LIMIT 1',
                ['slug' => $slug, 'cid' => $companyId]
            );
        }

        return (new Role())->queryOne(
            'SELECT * FROM rateb_roles WHERE slug = :slug AND company_id IS NULL LIMIT 1',
            ['slug' => $slug]
        );
    }

    public function dedupeDuplicateRoles(): int
    {
        $db = \Rateb\App\Core\Database::connection();

        $db->exec(
            'INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
             SELECT keeper.id, rp.permission_id
             FROM rateb_roles dup
             INNER JOIN rateb_roles keeper
                ON keeper.slug = dup.slug
               AND (keeper.company_id <=> dup.company_id)
               AND keeper.id < dup.id
             INNER JOIN rateb_role_permissions rp ON rp.role_id = dup.id'
        );

        $db->exec(
            'INSERT IGNORE INTO rateb_user_roles (user_id, role_id)
             SELECT ur.user_id, keeper.id
             FROM rateb_roles dup
             INNER JOIN rateb_roles keeper
                ON keeper.slug = dup.slug
               AND (keeper.company_id <=> dup.company_id)
               AND keeper.id < dup.id
             INNER JOIN rateb_user_roles ur ON ur.role_id = dup.id'
        );

        return $db->exec(
            'DELETE r1 FROM rateb_roles r1
             INNER JOIN rateb_roles r2
                ON r1.slug = r2.slug
               AND (r1.company_id <=> r2.company_id)
               AND r1.id > r2.id'
        );
    }

    /** True when company already has the expected tenant role slugs (skip GET bootstrap). */
    public function companyHasTenantRoleBootstrap(int $companyId): bool
    {
        if ($companyId < 1) {
            return true;
        }
        $slugs = self::tenantRoleSlugs();
        if ($slugs === []) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $params = array_merge([$companyId], $slugs);
        $row = (new Role())->queryOne(
            'SELECT COUNT(DISTINCT slug) AS c FROM rateb_roles
             WHERE company_id = ? AND slug IN (' . $placeholders . ')',
            $params
        );

        return (int) ($row['c'] ?? 0) >= count($slugs);
    }

    /** @return array<int, array<int, int>> roleId => permission ids */
    public function rolePermissionMatrix(?int $companyId = null): array
    {
        return $this->rolePermissionMatrixForRoles($this->allRoles($companyId));
    }

    /**
     * One query for all role↔permission links (avoids N+1 on matrix pages).
     *
     * @param list<array<string, mixed>> $roles
     * @return array<int, array<int, int>> roleId => permission ids
     */
    public function rolePermissionMatrixForRoles(array $roles): array
    {
        $matrix = [];
        $ids = [];
        foreach ($roles as $role) {
            $id = (int) ($role['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $matrix[$id] = [];
            $ids[] = $id;
        }
        if ($ids === []) {
            return $matrix;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = (new Permission())->query(
            'SELECT role_id, permission_id FROM rateb_role_permissions WHERE role_id IN (' . $placeholders . ')',
            $ids
        );
        foreach ($rows as $row) {
            $rid = (int) ($row['role_id'] ?? 0);
            if (!isset($matrix[$rid])) {
                continue;
            }
            $matrix[$rid][] = (int) ($row['permission_id'] ?? 0);
        }

        return $matrix;
    }

    /** @param array<int|string, array<int|string>> $matrix roleId => permission ids */
    public function syncMatrixFromPost(array $matrix, ?int $companyId = null): void
    {
        foreach ($this->allRoles($companyId) as $role) {
            $roleId = (int) $role['id'];
            $permIds = array_map('intval', (array) ($matrix[$roleId] ?? $matrix[(string) $roleId] ?? []));
            $this->syncRolePermissions($roleId, $permIds);
        }
    }

    public function getUserRoleNames(int $userId): string
    {
        $params = ['uid' => $userId];
        $sql = 'SELECT r.name FROM rateb_roles r
             JOIN rateb_user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :uid';
        $scope = $this->roleCompanyScopeSql($userId, $params);
        if ($scope === '' && !$this->userIsSuperAdmin($userId)) {
            return '';
        }
        $sql .= $scope . ' ORDER BY r.name';
        $rows = (new Role())->query($sql, $params);

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
            ['slug' => 'branch_manager', 'name' => 'Branch Manager', 'description' => 'Single-branch manager — inventory, procurement, branch KPIs', 'is_system' => true, 'permissions' => BranchAccessService::branchRolePermissionSlugs('branch_manager')],
            ['slug' => 'branch_user', 'name' => 'Branch User', 'description' => 'Single-branch read-only operator — branch dashboard and reports', 'is_system' => true, 'permissions' => BranchAccessService::branchRolePermissionSlugs('branch_user')],
            ['slug' => 'procurement-manager', 'name' => 'Procurement Manager', 'description' => 'Manage purchase requests, orders, and RFQ', 'is_system' => true, 'permissions' => ['procurement.manage', 'dashboard.view', 'reports.view']],
            ['slug' => 'inventory-manager', 'name' => 'Inventory Manager', 'description' => 'Manage inventory, warehouses, and stock', 'is_system' => true, 'permissions' => ['inventory.manage', 'dashboard.view', 'reports.view']],
            ['slug' => 'hr-manager', 'name' => 'HR Manager', 'description' => 'Manage employees, attendance, and payroll', 'is_system' => true, 'permissions' => ['hr.view', 'hr.manage', 'dashboard.view', 'reports.view']],
        ];
    }

    /** @return array{slug:string,name:string,description:string,is_system:bool,permissions:list<string>|null}|null */
    private static function suggestedDefinitionForSlug(string $slug): ?array
    {
        foreach (self::suggestedRoleDefinitions() as $def) {
            if ((string) ($def['slug'] ?? '') === $slug) {
                return $def;
            }
        }

        return null;
    }

    public function ensureSuggestedRoles(): void
    {
        $roleModel = new Role();
        $platformSlugs = self::platformRoleSlugs();
        foreach (self::suggestedRoleDefinitions() as $def) {
            $slug = (string) $def['slug'];
            if (!in_array($slug, $platformSlugs, true)) {
                continue;
            }
            $existing = $roleModel->queryOne(
                'SELECT id FROM rateb_roles WHERE slug = :slug AND company_id IS NULL LIMIT 1',
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
            $this->grantRolePermissionsBySlugs($roleId, $def['permissions']);
        }
    }

    public function ensureCompanyRoles(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $this->ensureSuggestedRoles();
        $roleModel = new Role();
        foreach (self::tenantRoleSlugs() as $slug) {
            $def = self::suggestedDefinitionForSlug($slug);
            if ($def === null) {
                continue;
            }
            $existing = $roleModel->queryOne(
                'SELECT id FROM rateb_roles WHERE slug = :slug AND company_id = :cid LIMIT 1',
                ['slug' => $slug, 'cid' => $companyId]
            );
            $roleId = (int) ($existing['id'] ?? 0);
            $created = false;
            if ($roleId < 1) {
                $roleId = $roleModel->create([
                    'company_id' => $companyId,
                    'name' => (string) $def['name'],
                    'slug' => $slug,
                    'description' => (string) $def['description'],
                    'is_system' => !empty($def['is_system']) ? 1 : 0,
                ]);
                $created = $roleId > 0;
            }
            if ($roleId < 1) {
                continue;
            }
            if ($def['permissions'] === ['__company_full_access__']) {
                // Seed full catalog only on first create — never wipe a saved custom matrix.
                if ($created) {
                    $this->syncCompanyFullAccessPermissions($roleId);
                    if (self::isAgencyPermissionMatrixContext()) {
                        $extra = (array) (self::permissionsConfig()['dedicated_company_admin_slugs'] ?? []);
                        if ($extra !== []) {
                            $this->grantRolePermissionsBySlugs($roleId, $extra);
                        }
                    }
                }
                continue;
            }
            if (is_array($def['permissions'])) {
                $this->grantRolePermissionsBySlugs($roleId, $def['permissions']);
            }
        }
        $this->syncBranchRolePermissionCatalogForCompany($companyId);
        $this->syncPosRolePermissionCatalogForCompany($companyId);
    }

    /** Idempotently grant branch role permission bundles for one company. */
    public function syncBranchRolePermissionCatalogForCompany(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $roleModel = new Role();
        foreach (BranchAccessService::branchRoleSlugs() as $slug) {
            $permSlugs = BranchAccessService::branchRolePermissionSlugs($slug);
            if ($permSlugs === []) {
                continue;
            }
            $row = $roleModel->queryOne(
                'SELECT id FROM rateb_roles WHERE slug = :slug AND company_id = :cid LIMIT 1',
                ['slug' => $slug, 'cid' => $companyId]
            );
            $roleId = (int) ($row['id'] ?? 0);
            if ($roleId > 0) {
                $this->grantRolePermissionsBySlugs($roleId, $permSlugs);
            }
        }
    }

    public function syncPosRolePermissionCatalogForCompany(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $bundles = [
            'pos_cashier' => ['pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open'],
            'pos_supervisor' => [
                'pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open', 'pos.shift.close',
                'pos.discount.manage', 'pos.returns.manage', 'pos.reports.view', 'pos.cash_drawer.manage',
                'pos.orders.view', 'pos.inventory.adjust', 'pos.supervisor.approve', 'pos.payment.record',
            ],
            'pos_manager' => [
                'pos.view', 'pos.register', 'pos.sale.complete', 'pos.shift.open', 'pos.shift.close',
                'pos.discount.manage', 'pos.returns.manage', 'pos.reports.view', 'pos.cash_drawer.manage',
                'pos.orders.view', 'pos.terminals.manage', 'pos.settings.manage', 'pos.devices.manage', 'pos.sync.manage',
            ],
        ];
        $roleModel = new Role();
        foreach ($bundles as $slug => $permSlugs) {
            $row = $roleModel->queryOne(
                'SELECT id FROM rateb_roles WHERE slug = :slug AND company_id = :cid LIMIT 1',
                ['slug' => $slug, 'cid' => $companyId]
            );
            $roleId = (int) ($row['id'] ?? 0);
            if ($roleId > 0) {
                $this->grantRolePermissionsBySlugs($roleId, $permSlugs);
            }
        }
    }

    /** Idempotently grant branch role permission bundles to every branch_manager / branch_user role row. */
    public function syncBranchRolePermissionCatalog(): void
    {
        $roleModel = new Role();
        $companyIds = $roleModel->query(
            'SELECT DISTINCT company_id FROM rateb_roles WHERE company_id IS NOT NULL AND company_id > 0'
        );
        foreach ($companyIds as $row) {
            $this->syncBranchRolePermissionCatalogForCompany((int) ($row['company_id'] ?? 0));
        }
    }

    /** @param list<string> $slugs */
    private function grantRolePermissionsBySlugs(int $roleId, array $slugs): void
    {
        if ($roleId < 1 || $slugs === []) {
            return;
        }
        if ($slugs === ['__company_full_access__']) {
            // Additive only — never wipe an admin-customized matrix.
            $this->grantCompanyFullAccessPermissions($roleId);

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
        $this->syncRolePermissions($roleId, $this->companyFullAccessPermissionIds());
    }

    /** Add missing full-access permissions without removing customized ones. */
    private function grantCompanyFullAccessPermissions(int $roleId): void
    {
        if ($roleId < 1) {
            return;
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id) VALUES (:rid, :pid)'
        );
        foreach ($this->companyFullAccessPermissionIds() as $pid) {
            $stmt->execute(['rid' => $roleId, 'pid' => $pid]);
        }
    }

    /** @return list<int> */
    private function companyFullAccessPermissionIds(): array
    {
        $config = self::permissionsConfig();
        // Keep access.manage / settings.manage / notifications.manage excluded from bulk grant
        // so matrix unchecks stick (seeded only on first role create via dedicated_company_admin_slugs).
        $excluded = (array) ($config['company_role_excluded_slugs'] ?? []);
        $rows = (new Permission())->query('SELECT id, slug FROM rateb_permissions');
        $ids = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '' || in_array($slug, $excluded, true)) {
                continue;
            }
            $pid = (int) ($row['id'] ?? 0);
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }

        return $ids;
    }

    public function refreshDedicatedCompanyAccessPermissions(): void
    {
        $companyId = self::resolveMatrixCompanyId();
        if ($companyId > 0) {
            $this->refreshTenantSelfServicePermissions($companyId);
        }
    }

    public function refreshTenantSelfServicePermissions(int $companyId): void
    {
        if ($companyId < 1 || !self::isAgencyPermissionMatrixContext()) {
            return;
        }
        $this->ensureTenantSelfServicePermissionRows();
        $this->ensureCompanyRoles($companyId);
        $role = $this->findRoleBySlug('company-full-access', $companyId);
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];
        // Additive ops modules only — never re-force access/settings/notifications
        // (those stay under the saved role matrix after first seed).
        $this->grantCompanyFullAccessPermissions($roleId);
    }

    /**
     * Ensure access/settings/notifications permission rows exist (agency DBs may lack them).
     */
    public function ensureTenantSelfServicePermissionRows(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $defs = [
            [
                'slug' => 'access.manage',
                'module' => 'access',
                'name' => 'Manage Access Control',
                'name_ar' => 'إدارة التحكم بالوصول',
                'description' => 'Users, roles, permissions matrix, and access control',
                'description_ar' => 'المستخدمون، الأدوار، مصفوفة الصلاحيات، والتحكم بالوصول',
            ],
            [
                'slug' => 'settings.manage',
                'module' => 'settings',
                'name' => 'Manage Settings',
                'name_ar' => 'إدارة الإعدادات',
                'description' => 'Support tickets, audit log, email/SMS templates, and system settings',
                'description_ar' => 'تذاكر الدعم، سجل التدقيق، قوالب البريد والرسائل، وإعدادات النظام',
            ],
            [
                'slug' => 'notifications.manage',
                'module' => 'notifications',
                'name' => 'Manage Notifications',
                'name_ar' => 'إدارة الإشعارات',
                'description' => 'Notification center and preferences',
                'description_ar' => 'مركز الإشعارات وتفضيلاتها',
            ],
            [
                'slug' => 'dashboard.view',
                'module' => 'dashboard',
                'name' => 'View Dashboard',
                'name_ar' => 'عرض لوحة التحكم',
                'description' => 'Access the main dashboard',
                'description_ar' => 'الوصول إلى لوحة التحكم',
            ],
            [
                'slug' => 'help.view',
                'module' => 'help',
                'name' => 'View Help Center',
                'name_ar' => 'عرض مركز المساعدة',
                'description' => 'Access in-app Help Center',
                'description_ar' => 'الوصول لمركز المساعدة داخل النظام',
            ],
            [
                'slug' => 'help.manage',
                'module' => 'help',
                'name' => 'Manage Help Center',
                'name_ar' => 'إدارة مركز المساعدة',
                'description' => 'Manage Help Center content',
                'description_ar' => 'إدارة محتوى مركز المساعدة',
            ],
        ];
        $model = new Permission();
        foreach ($defs as $def) {
            $existing = $model->queryOne(
                'SELECT id FROM rateb_permissions WHERE slug = :slug LIMIT 1',
                ['slug' => $def['slug']]
            );
            if ($existing) {
                continue;
            }
            try {
                $model->create([
                    'name' => $def['name'],
                    'name_ar' => $def['name_ar'],
                    'slug' => $def['slug'],
                    'module' => $def['module'],
                    'description' => $def['description'],
                    'description_ar' => $def['description_ar'],
                ]);
            } catch (\Throwable $e) {
                // best-effort — unique race or missing columns
            }
        }
    }

    /** Ensure agency company admin has company-full-access role (idempotent). */
    public function ensureAgencyCompanyAdminRole(int $userId): void
    {
        if ($userId < 1 || !self::isAgencyPermissionMatrixContext()) {
            return;
        }
        $user = (new User())->find($userId);
        if (!$user || !empty($user['is_super_admin'])) {
            return;
        }
        $companyId = (int) ($user['company_id'] ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        $eligible = $companyId > 0;
        if (!$eligible) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $name = strtolower(trim((string) ($user['name'] ?? '')));
            $eligible = $email === 'admin@local' || $name === 'admin' || str_starts_with($email, 'admin+');
        }
        if (!$eligible) {
            return;
        }
        if ($companyId > 0) {
            $this->ensureCompanyRoles($companyId);
        }
        $role = $this->findRoleBySlug('company-full-access', $companyId > 0 ? $companyId : null);
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];
        $existing = (new Role())->queryOne(
            'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
            ['uid' => $userId, 'rid' => $roleId]
        );
        if ($existing === null) {
            $this->assignRole($userId, $roleId);
        }
    }

    private static function isAgencyPermissionMatrixContext(): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }

        return function_exists('rateb_tenant_permission_catalog_locked') && rateb_tenant_permission_catalog_locked();
    }
}
