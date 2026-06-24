<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\TenantContext;

/** Central branch isolation — all controllers/services should use this for raw SQL. */
final class BranchIsolationService
{
    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    public function sqlFilter(string $alias = '', string $column = 'branch_id'): array
    {
        if (!function_exists('rateb_branch_filter_sql')) {
            return ['', []];
        }
        return rateb_branch_filter_sql($alias, $column);
    }

    /** @param array<string, mixed> $data */
    public function stampCreate(array $data): array
    {
        if (!array_key_exists('branch_id', $data) || empty($data['branch_id'])) {
            if (function_exists('rateb_resolve_create_branch_id')) {
                $branchId = rateb_resolve_create_branch_id();
                if ($branchId > 0) {
                    $data['branch_id'] = $branchId;
                }
            }
        }
        if (!array_key_exists('company_id', $data) || empty($data['company_id'])) {
            $companyId = TenantContext::companyId();
            if ($companyId !== null && $companyId > 0) {
                $data['company_id'] = $companyId;
            }
        }
        return $data;
    }

    public function assertCanAccess(int $branchId): void
    {
        (new BranchAccessService())->assertCanAccess($branchId);
    }

    /** @param array<string, mixed> $params */
    public function appendFilter(string $sql, array $params, string $alias = '', string $column = 'branch_id'): array
    {
        [$extra, $extraParams] = $this->sqlFilter($alias, $column);
        if ($extra === '') {
            return [$sql, $params];
        }
        $upper = strtoupper($sql);
        foreach ([' GROUP BY ', ' ORDER BY ', ' LIMIT '] as $token) {
            $pos = strpos($upper, $token);
            if ($pos !== false) {
                return [substr($sql, 0, $pos) . $extra . substr($sql, $pos), array_merge($params, $extraParams)];
            }
        }
        return [$sql . (preg_match('/\bWHERE\b/i', $sql) ? $extra : ' WHERE 1=1' . $extra), array_merge($params, $extraParams)];
    }

    /** @return array<int, int> */
    public function effectiveBranchIds(): array
    {
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context();
        }
        $ids = BranchContext::effectiveFilterIds();
        if ($ids !== []) {
            return $ids;
        }
        if (BranchContext::accessAll()) {
            $cid = BranchContext::companyId();
            return $cid > 0 ? (new BranchAccessService())->allowedBranchIds($cid) : [];
        }
        return BranchContext::allowedIds();
    }
}
