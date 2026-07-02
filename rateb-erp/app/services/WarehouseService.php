<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\BranchContext;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Warehouse;

final class WarehouseService
{
    public const MAIN_CODE = 'WH-MAIN';

    public function countForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        return (new Warehouse())->count(['company_id' => $companyId, 'status' => 'active']);
    }

    /** Ensure at least one active warehouse exists for the company (linked to main branch when possible). */
    public function ensureDefaultWarehouse(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $existing = (new Warehouse())->queryOne(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND code = :code ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId, 'code' => self::MAIN_CODE]
        );
        if (!$existing) {
            $existing = (new Warehouse())->queryOne(
                'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND status = :st ORDER BY id ASC LIMIT 1',
                ['cid' => $companyId, 'st' => 'active']
            );
        }
        if ($existing) {
            $this->backfillBranchLinks($companyId);
            return (int) ($existing['id'] ?? 0);
        }

        $branchId = (new BranchService())->defaultBranchId($companyId);
        $prevCompany = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            $id = (new Warehouse())->create([
                'company_id' => $companyId,
                'name' => __('main_warehouse'),
                'code' => self::MAIN_CODE,
                'location' => '',
                'status' => 'active',
                'branch_id' => $branchId > 0 ? $branchId : null,
            ]);
            return $id;
        } finally {
            TenantContext::setCompanyId($prevCompany);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listActiveForCompany(int $companyId, int $limit = 300): array
    {
        if ($companyId < 1) {
            return [];
        }
        $sql = 'SELECT id, code, name, branch_id FROM rateb_warehouses
                WHERE company_id = :cid AND status = :st';
        $params = ['cid' => $companyId, 'st' => 'active'];

        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        $branchIds = BranchContext::effectiveFilterIds();
        if ($branchIds !== []) {
            $parts = ['branch_id IS NULL'];
            foreach ($branchIds as $i => $bid) {
                $key = 'wb_' . $i;
                $parts[] = 'branch_id = :' . $key;
                $params[$key] = $bid;
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }

        $sql .= ' ORDER BY name ASC LIMIT ' . max(1, min(500, $limit));
        return (new Warehouse())->query($sql, $params);
    }

    private function backfillBranchLinks(int $companyId): void
    {
        $branchId = (new BranchService())->defaultBranchId($companyId);
        if ($branchId < 1) {
            return;
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->prepare(
                'UPDATE rateb_warehouses SET branch_id = :bid
                 WHERE company_id = :cid AND branch_id IS NULL LIMIT 50'
            );
            $stmt->execute(['bid' => $branchId, 'cid' => $companyId]);
        } catch (\Throwable $e) {
            // column may be missing on older schemas
        }
    }
}
