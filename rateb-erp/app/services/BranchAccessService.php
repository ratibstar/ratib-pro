<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

/**
 * Resolves branch visibility per company user.
 *
 * Multi-company: company_id (TenantContext) isolates tenants.
 * Access resolution: resolveBranchAccess() — super admin, branches.access_all, HQ roles,
 * branch-restricted roles (strict flag), then legacy empty-junction behavior.
 */
final class BranchAccessService
{
    /** @var array<int, string> */
    public const BRANCH_RESTRICTED_ROLE_SLUGS = ['branch_manager', 'branch_user'];

    private static bool $bootstrapping = false;

    public function bootstrap(?int $companyId = null): void
    {
        if (BranchContext::isBootstrapped()) {
            return;
        }
        if (self::$bootstrapping) {
            return;
        }
        self::$bootstrapping = true;
        try {
            $this->doBootstrap($companyId);
        } finally {
            self::$bootstrapping = false;
        }
    }

    private function doBootstrap(?int $companyId): void
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            BranchContext::setBootstrapped(0, true, []);
            return;
        }

        if (!BranchService::branchesTableExists()) {
            BranchContext::setBootstrapped($companyId, true, []);
            return;
        }

        $portalBranch = (int) SessionManager::get('rateb_portal_branch_id', 0);
        if ($portalBranch > 0) {
            $row = (new Branch())->queryOne(
                'SELECT id FROM rateb_branches WHERE id = :id AND company_id = :cid AND status = :st LIMIT 1',
                ['id' => $portalBranch, 'cid' => $companyId, 'st' => 'active']
            );
            if ($row) {
                BranchContext::setBootstrapped($companyId, false, [(int) $row['id']]);
                return;
            }
            SessionManager::forget('rateb_portal_branch_id');
        }

        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            $all = $this->branchIdsForCompany($companyId);
            BranchContext::setBootstrapped($companyId, true, $all);
            $this->applyHeadOfficeBranchFilter();
            return;
        }

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1) {
            BranchContext::setBootstrapped($companyId, true, $this->branchIdsForCompany($companyId));
            return;
        }

        $assigned = (new BranchService())->getUserBranchIds($userId);
        $companyBranchIds = $this->branchIdsForCompany($companyId);
        $resolved = $this->resolveBranchAccess(
            $companyId,
            $userId,
            $assigned,
            $companyBranchIds,
            false,
            $this->userHasAccessAllPermission(),
            $this->userHasHeadOfficeRole()
        );
        BranchContext::setBootstrapped($companyId, $resolved['accessAll'], $resolved['allowedIds']);
        if ($resolved['accessAll']) {
            $this->applyHeadOfficeBranchFilter();
        }
    }

    /** API tokens: no session — resolve branch access from user + optional token branch claim. */
    public function bootstrapForApi(int $companyId, int $userId, ?int $tokenBranchId = null): void
    {
        BranchContext::reset();
        if ($companyId < 1) {
            BranchContext::setBootstrapped(0, true, []);
            return;
        }

        $companyBranchIds = $this->branchIdsForCompany($companyId);
        if ($userId < 1) {
            BranchContext::setBootstrapped($companyId, true, $companyBranchIds);
            if ($tokenBranchId !== null && $tokenBranchId > 0) {
                BranchContext::setBootstrapped($companyId, false, [$tokenBranchId]);
            }
            return;
        }

        $assigned = (new BranchService())->getUserBranchIds($userId);
        $resolved = $this->resolveBranchAccess(
            $companyId,
            $userId,
            $assigned,
            $companyBranchIds,
            $this->userIsSuperAdmin($userId),
            $this->userHasAccessAllPermissionForUser($userId, $companyId),
            $this->userHasHeadOfficeRoleForUser($userId, $companyId)
        );
        BranchContext::setBootstrapped($companyId, $resolved['accessAll'], $resolved['allowedIds']);

        if ($tokenBranchId !== null && $tokenBranchId > 0) {
            if (!$this->canAccessBranch($tokenBranchId)) {
                BranchContext::setBootstrapped($companyId, false, []);
                return;
            }
            BranchContext::setBootstrapped($companyId, false, [$tokenBranchId]);
            BranchContext::setActiveFilterBranchId($tokenBranchId);
        }
    }

    private function userHasAccessAllPermissionForUser(int $userId, int $companyId): bool
    {
        return (new AuthorizationService())->userHasPermission($userId, 'branches.access_all');
    }

    private function userHasHeadOfficeRoleForUser(int $userId, int $companyId): bool
    {
        $row = (new \Rateb\App\Models\User())->queryOne(
            'SELECT r.slug FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = :cid)
               AND r.slug IN (\'hq_admin\', \'hq_manager\', \'company-full-access\')
             LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        return $row !== null;
    }

    /**
     * Single source of truth for session (doBootstrap) and API (bootstrapForApi) branch access.
     *
     * @param array<int, int> $assigned
     * @param array<int, int> $companyBranchIds
     * @return array{accessAll: bool, allowedIds: array<int, int>}
     */
    private function resolveBranchAccess(
        int $companyId,
        int $userId,
        array $assigned,
        array $companyBranchIds,
        bool $isSuperAdmin,
        bool $hasAccessAllPermission,
        bool $hasHeadOfficeRole
    ): array {
        if ($isSuperAdmin || $hasAccessAllPermission || $hasHeadOfficeRole) {
            return ['accessAll' => true, 'allowedIds' => $companyBranchIds];
        }

        if ($assigned === [] && $this->userHasBranchRestrictedRole($userId, $companyId)) {
            if (function_exists('rateb_branch_strict_assignment') && rateb_branch_strict_assignment()) {
                return ['accessAll' => false, 'allowedIds' => []];
            }

            return ['accessAll' => true, 'allowedIds' => $companyBranchIds];
        }

        if ($assigned === []) {
            return ['accessAll' => true, 'allowedIds' => $companyBranchIds];
        }

        $allowed = array_values(array_intersect($assigned, $companyBranchIds));
        if ($allowed === []) {
            $main = (new BranchService())->defaultBranchId($companyId);
            $allowed = $main > 0 ? [$main] : [];
        }

        return ['accessAll' => false, 'allowedIds' => $allowed];
    }

    private function userIsSuperAdmin(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $row = (new \Rateb\App\Models\User())->queryOne(
            'SELECT is_super_admin FROM rateb_users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );

        return !empty($row['is_super_admin']);
    }

    /** HQ users with access_all may narrow to one branch via session switcher. */
    private function applyHeadOfficeBranchFilter(): void
    {
        if (!BranchContext::accessAll()) {
            return;
        }
        $filterId = (int) SessionManager::get('rateb_active_branch_filter', 0);
        if ($filterId > 0 && $this->canAccessBranch($filterId)) {
            BranchContext::setActiveFilterBranchId($filterId);
        }
    }

    public function setActiveBranchFilter(int $branchId): bool
    {
        $this->bootstrap();
        if ($branchId < 1) {
            SessionManager::forget('rateb_active_branch_filter');
            BranchContext::setActiveFilterBranchId(null);
            return true;
        }
        if (!BranchContext::accessAll() || !$this->canAccessBranch($branchId)) {
            return false;
        }
        SessionManager::set('rateb_active_branch_filter', $branchId);
        BranchContext::setActiveFilterBranchId($branchId);
        return true;
    }

    public function clearActiveBranchFilter(): void
    {
        SessionManager::forget('rateb_active_branch_filter');
        BranchContext::setActiveFilterBranchId(null);
    }

    public function userBranchRoleSlug(): string
    {
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1) {
            return '';
        }
        $row = (new \Rateb\App\Models\User())->queryOne(
            'SELECT r.slug FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = :cid)
             ORDER BY FIELD(r.slug, \'hq_admin\', \'hq_manager\', \'branch_manager\', \'branch_user\', \'company-full-access\') ASC
             LIMIT 1',
            ['uid' => $userId, 'cid' => BranchContext::companyId()]
        );
        return (string) ($row['slug'] ?? '');
    }

    public function isHeadOfficeUser(): bool
    {
        return BranchContext::accessAll();
    }

    public function canManageAllBranches(): bool
    {
        $this->bootstrap();
        if (!BranchContext::accessAll()) {
            return false;
        }
        return function_exists('rateb_can') && rateb_can('branches.manage');
    }

    public function assertCanAccess(int $branchId): void
    {
        $this->bootstrap();
        if (!$this->canAccessBranch($branchId)) {
            throw new \RuntimeException(__('branch_access_denied'));
        }
    }

    public function canAccessBranch(int $branchId): bool
    {
        $this->bootstrap();
        return BranchContext::canAccess($branchId);
    }

    /** @return array<int, int> */
    public function allowedBranchIds(?int $companyId = null): array
    {
        $this->bootstrap($companyId);
        if (BranchContext::accessAll()) {
            $cid = $companyId ?? BranchContext::companyId();
            return $cid > 0 ? $this->branchIdsForCompany($cid) : [];
        }
        return BranchContext::allowedIds();
    }

    /** @return array<int, string> */
    public static function branchRestrictedRoleSlugs(): array
    {
        return self::BRANCH_RESTRICTED_ROLE_SLUGS;
    }

    public function slugRequiresBranchAssignment(string $slug): bool
    {
        return in_array($slug, self::BRANCH_RESTRICTED_ROLE_SLUGS, true);
    }

    /** @param array<int, string> $roleSlugs */
    public function roleSlugsRequireBranchAssignment(array $roleSlugs): bool
    {
        foreach ($roleSlugs as $slug) {
            if ($this->slugRequiresBranchAssignment((string) $slug)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, int|string> $roleIds */
    public function roleIdsRequireBranchAssignment(array $roleIds): bool
    {
        return $this->roleSlugsRequireBranchAssignment($this->roleSlugsForIds($roleIds));
    }

    public function userHasBranchRestrictedRole(int $userId, ?int $companyId = null): bool
    {
        if ($userId < 1) {
            return false;
        }
        $companyId = $companyId ?? $this->resolveCompanyId(null);

        return $this->userHasRoleSlugAmong(
            $userId,
            $companyId > 0 ? $companyId : 0,
            self::BRANCH_RESTRICTED_ROLE_SLUGS
        );
    }

    /** @param array<int, int|string> $roleIds @return array<int, string> */
    private function roleSlugsForIds(array $roleIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = \Rateb\App\Core\Database::connection()->prepare(
            'SELECT slug FROM rateb_roles WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['slug'] ?? ''),
            is_array($rows) ? $rows : []
        )));
    }

    /** @param array<int, string> $slugs */
    private function userHasRoleSlugAmong(int $userId, int $companyId, array $slugs): bool
    {
        if ($slugs === []) {
            return false;
        }
        $slugParams = [];
        $slugPlaceholders = [];
        foreach (array_values($slugs) as $i => $slug) {
            $key = 'slug_' . $i;
            $slugParams[$key] = $slug;
            $slugPlaceholders[] = ':' . $key;
        }
        $sql = 'SELECT r.slug FROM rateb_user_roles ur
             INNER JOIN rateb_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid
               AND (r.company_id IS NULL OR r.company_id = 0 OR r.company_id = :cid)
               AND r.slug IN (' . implode(',', $slugPlaceholders) . ')
             LIMIT 1';
        $params = array_merge(['uid' => $userId, 'cid' => $companyId], $slugParams);
        $row = (new \Rateb\App\Models\User())->queryOne($sql, $params);

        return $row !== null;
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }
        $ctx = TenantContext::companyId();
        if ($ctx !== null && $ctx > 0) {
            return (int) $ctx;
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            return rateb_resolve_ops_company_id();
        }
        return (int) SessionManager::get('rateb_company_id', 0);
    }

    private function userHasAccessAllPermission(): bool
    {
        return function_exists('rateb_can') && rateb_can('branches.access_all');
    }

    private function userHasHeadOfficeRole(): bool
    {
        $slug = $this->userBranchRoleSlug();
        return in_array($slug, ['hq_admin', 'hq_manager', 'company-full-access', 'super-admin'], true);
    }

    /** @return array<int, int> */
    private function branchIdsForCompany(int $companyId): array
    {
        $rows = (new Branch())->query(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY '
            . BranchService::branchOrderSql(),
            ['cid' => $companyId, 'st' => 'active']
        );
        return array_map('intval', array_column($rows, 'id'));
    }
}
