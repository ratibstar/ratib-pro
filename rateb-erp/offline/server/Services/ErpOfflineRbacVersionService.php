<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Services\AuthorizationService;
use Rateb\App\Services\PlanLimitService;

/**
 * Phase 12 — Deterministic RBAC version from live authz/plan/branch state.
 * Role / permission / user-role / plan-module / branch-access changes alter the hash,
 * so online clients delete stale snapshots and refresh.
 */
final class ErpOfflineRbacVersionService
{
    /**
     * @return array{
     *   rbac_version: string,
     *   role_ids: list<int>,
     *   permission_slugs: list<string>,
     *   plan_modules: list<string>,
     *   branch_ids: list<int>
     * }
     */
    public function fingerprint(int $companyId, int $userId, int $branchId = 0): array
    {
        $authz = new AuthorizationService();
        $roleIds = $authz->getUserRoleIds($userId);
        sort($roleIds);
        $slugs = $authz->userPermissionSlugs($userId);
        sort($slugs);
        $modules = [];
        if ($companyId > 0) {
            $modules = (new PlanLimitService())->getLimits($companyId)['modules'] ?? [];
            $modules = is_array($modules) ? array_values(array_map('strval', $modules)) : [];
            sort($modules);
        }
        $branchIds = $this->branchAccessIds($branchId);
        sort($branchIds);

        $payload = [
            'company_id' => $companyId,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'role_ids' => $roleIds,
            'permission_slugs' => $slugs,
            'plan_modules' => $modules,
            'branch_ids' => $branchIds,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $version = hash('sha256', is_string($json) ? $json : '');

        return [
            'rbac_version' => $version,
            'role_ids' => $roleIds,
            'permission_slugs' => $slugs,
            'plan_modules' => $modules,
            'branch_ids' => $branchIds,
        ];
    }

    public function currentVersion(int $companyId, int $userId, int $branchId = 0): string
    {
        return $this->fingerprint($companyId, $userId, $branchId)['rbac_version'];
    }

    /** @return list<int> */
    private function branchAccessIds(int $portalBranchId): array
    {
        $ids = [];
        if ($portalBranchId > 0) {
            $ids[] = $portalBranchId;
        }
        try {
            if (class_exists(BranchContext::class)) {
                foreach (BranchContext::effectiveFilterIds() as $id) {
                    $ids[] = (int) $id;
                }
            }
        } catch (\Throwable $e) {
            // Fail soft — version still includes portal branch + roles/perms/modules.
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        return $ids;
    }
}
