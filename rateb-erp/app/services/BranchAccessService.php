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
 * Per company: empty rateb_user_branches = HQ (all branches); assigned rows = branch-only.
 */
final class BranchAccessService
{
    public function bootstrap(?int $companyId = null): void
    {
        if (BranchContext::isBootstrapped()) {
            return;
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            BranchContext::setBootstrapped(0, true, []);
            return;
        }

        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            $all = $this->branchIdsForCompany($companyId);
            BranchContext::setBootstrapped($companyId, true, $all);
            return;
        }

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1) {
            BranchContext::setBootstrapped($companyId, true, $this->branchIdsForCompany($companyId));
            return;
        }

        $assigned = (new BranchService())->getUserBranchIds($userId);
        $companyBranchIds = $this->branchIdsForCompany($companyId);

        if ($assigned === [] || $this->userHasAccessAllPermission()) {
            BranchContext::setBootstrapped($companyId, true, $companyBranchIds);
            return;
        }

        $allowed = array_values(array_intersect($assigned, $companyBranchIds));
        if ($allowed === []) {
            $main = (new BranchService())->defaultBranchId($companyId);
            $allowed = $main > 0 ? [$main] : [];
        }

        BranchContext::setBootstrapped($companyId, false, $allowed);
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

    /** @return array<int, int> */
    private function branchIdsForCompany(int $companyId): array
    {
        $rows = (new Branch())->query(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY is_main DESC, id ASC',
            ['cid' => $companyId, 'st' => 'active']
        );
        return array_map('intval', array_column($rows, 'id'));
    }
}
