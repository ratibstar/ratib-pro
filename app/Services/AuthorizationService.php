<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AuthorizationService
{
    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, string> */
    private array $columnCache = [];

    /** @var array<int, list<string>> */
    private array $userPermissionCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, mixed> $user */
    public function can(array $user, string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }
        $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        $permissions = $this->loadUserPermissions($userId);
        if ($permissions === []) {
            return false;
        }

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /** @param array<string, mixed> $user */
    public function enforce(array $user, string $permission): void
    {
        if (!$this->can($user, $permission)) {
            throw new RuntimeException('Access denied: missing permission ' . $permission, 403);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $payload
     */
    public function enforceCountryScope(array $user, array $payload): void
    {
        $allowed = $this->resolveAllowedCountryIds($user);
        if ($allowed === null) {
            return;
        }
        if ($allowed === []) {
            throw new RuntimeException('Access denied: no country scope assigned', 403);
        }

        $requested = $this->resolveRequestedCountryId($payload);
        if ($requested <= 0) {
            return;
        }
        if (!in_array($requested, $allowed, true)) {
            throw new RuntimeException('Access denied: country scope violation', 403);
        }
    }

    /** @return list<string> */
    private function loadUserPermissions(int $userId): array
    {
        if (isset($this->userPermissionCache[$userId])) {
            return $this->userPermissionCache[$userId];
        }
        $out = [];
        if ($this->tableExists('user_roles') && $this->tableExists('role_permissions') && $this->tableExists('permissions') && $this->tableExists('roles')) {
            $roleIds = $this->loadUserRoleIds($userId);
            if ($roleIds !== []) {
                $roleIds = $this->expandRoleInheritance($roleIds);
                $out = $this->loadPermissionsForRoles($roleIds);
            }
        }

        $this->userPermissionCache[$userId] = array_values(array_unique($out));

        return $this->userPermissionCache[$userId];
    }

    /** @return list<int> */
    private function loadUserRoleIds(int $userId): array
    {
        $userIdCol = $this->columnExists('user_roles', 'user_id') ? 'user_id' : 'id';
        $roleIdCol = $this->columnExists('user_roles', 'role_id') ? 'role_id' : 'id';
        $sql = "SELECT {$roleIdCol} AS role_id FROM user_roles WHERE {$userIdCol} = :user_id";
        $st = $this->db->prepare($sql);
        $st->execute([':user_id' => $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = [];
        foreach ($rows as $r) {
            $rid = (int) ($r['role_id'] ?? 0);
            if ($rid > 0) {
                $ids[] = $rid;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $roleIds @return list<int> */
    private function expandRoleInheritance(array $roleIds): array
    {
        if ($roleIds === [] || !$this->columnExists('roles', 'parent_role_id')) {
            return $roleIds;
        }
        $rolePk = $this->rolesPrimaryKey();
        $seen = array_fill_keys($roleIds, true);
        $queue = $roleIds;
        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_int($current) || $current <= 0) {
                continue;
            }
            $st = $this->db->prepare("SELECT parent_role_id FROM roles WHERE {$rolePk} = :role_id LIMIT 1");
            $st->execute([':role_id' => $current]);
            $parent = (int) ($st->fetchColumn() ?: 0);
            if ($parent > 0 && !isset($seen[$parent])) {
                $seen[$parent] = true;
                $queue[] = $parent;
            }
        }

        return array_map('intval', array_keys($seen));
    }

    /** @param list<int> $roleIds @return list<string> */
    private function loadPermissionsForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $roleIdCol = $this->columnExists('role_permissions', 'role_id') ? 'role_id' : 'id';
        $permIdCol = $this->columnExists('role_permissions', 'permission_id') ? 'permission_id' : 'id';
        $permPk = $this->permissionsPrimaryKey();
        $permName = $this->columnExists('permissions', 'name') ? 'name' : 'permission';

        $sql = "SELECT p.{$permName} AS permission_name
                FROM role_permissions rp
                INNER JOIN permissions p ON p.{$permPk} = rp.{$permIdCol}
                WHERE rp.{$roleIdCol} IN ({$placeholders})";
        $st = $this->db->prepare($sql);
        foreach ($roleIds as $i => $rid) {
            $st->bindValue($i + 1, $rid, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $perm = trim((string) ($r['permission_name'] ?? ''));
            if ($perm !== '') {
                $out[] = $perm;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param array<string, mixed> $user @return list<int>|null */
    private function resolveAllowedCountryIds(array $user): ?array
    {
        if (isset($user['allowed_country_ids']) && is_array($user['allowed_country_ids'])) {
            $raw = array_values(array_unique(array_map('intval', $user['allowed_country_ids'])));
            return array_values(array_filter($raw, static fn (int $x): bool => $x > 0));
        }
        if (isset($_SESSION['allowed_country_ids']) && is_array($_SESSION['allowed_country_ids'])) {
            $raw = array_values(array_unique(array_map('intval', $_SESSION['allowed_country_ids'])));
            return array_values(array_filter($raw, static fn (int $x): bool => $x > 0));
        }
        $sessionCountry = (int) ($_SESSION['control_country_id'] ?? $_SESSION['country_id'] ?? 0);
        if ($sessionCountry > 0) {
            return [$sessionCountry];
        }
        if ($this->can($user, '*') || $this->can($user, 'country.scope.all')) {
            return null;
        }
        if (isset($user['country_id']) && (int) $user['country_id'] > 0) {
            return [(int) $user['country_id']];
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function resolveRequestedCountryId(array $payload): int
    {
        $candidate = (int) ($payload['country_id'] ?? $payload['control_country_id'] ?? 0);
        if ($candidate > 0) {
            return $candidate;
        }
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        if ($tenantId > 0 && $this->tableExists('control_agencies') && $this->columnExists('control_agencies', 'country_id') && $this->columnExists('control_agencies', 'tenant_id')) {
            $st = $this->db->prepare('SELECT country_id FROM control_agencies WHERE tenant_id = :tenant_id LIMIT 1');
            $st->execute([':tenant_id' => $tenantId]);
            return (int) ($st->fetchColumn() ?: 0);
        }

        return 0;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
        $st->execute([':table_name' => $table]);
        $exists = ((int) $st->fetchColumn()) > 0;
        $this->tableCache[$table] = $exists;

        return $exists;
    }

    private function columnExists(string $table, string $column): bool
    {
        $k = $table . '.' . $column;
        if (array_key_exists($k, $this->columnCache)) {
            return $this->columnCache[$k] === '1';
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name');
        $st->execute([':table_name' => $table, ':column_name' => $column]);
        $exists = ((int) $st->fetchColumn()) > 0;
        $this->columnCache[$k] = $exists ? '1' : '0';

        return $exists;
    }

    private function rolesPrimaryKey(): string
    {
        if ($this->columnExists('roles', 'id')) {
            return 'id';
        }
        if ($this->columnExists('roles', 'role_id')) {
            return 'role_id';
        }

        return 'id';
    }

    private function permissionsPrimaryKey(): string
    {
        if ($this->columnExists('permissions', 'id')) {
            return 'id';
        }
        if ($this->columnExists('permissions', 'permission_id')) {
            return 'permission_id';
        }

        return 'id';
    }
}
