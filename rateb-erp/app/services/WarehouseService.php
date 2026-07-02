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
        $pdo = \Rateb\App\Core\Database::connection();
        $lockName = 'rateb_wh_main_' . $companyId;
        $gotLock = false;
        try {
            $lockStmt = $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 8)");
            $gotLock = (int) $lockStmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            $gotLock = false;
        }

        try {
            $existingId = $this->findMainWarehouseIdUnscoped($pdo, $companyId);
            if ($existingId > 0) {
                $this->backfillBranchLinks($companyId);
                $this->dedupeMainWarehouses($companyId);
                return $existingId;
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
                $this->dedupeMainWarehouses($companyId);
                return $id;
            } finally {
                TenantContext::setCompanyId($prevCompany);
            }
        } finally {
            if ($gotLock) {
                try {
                    $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    public function dedupeMainWarehouses(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        try {
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND code = :code ORDER BY id ASC'
            );
            $stmt->execute(['cid' => $companyId, 'code' => self::MAIN_CODE]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) <= 1) {
                return;
            }
            $keepId = (int) ($rows[0]['id'] ?? 0);
            $del = $pdo->prepare(
                'DELETE FROM rateb_warehouses WHERE company_id = :cid AND code = :code AND id <> :keep'
            );
            $del->execute(['cid' => $companyId, 'code' => self::MAIN_CODE, 'keep' => $keepId]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function findMainWarehouseIdUnscoped(\PDO $pdo, int $companyId): int
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND code = :code ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => self::MAIN_CODE]);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }
        $stmt = $pdo->prepare(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND status = :st ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'st' => 'active']);

        return (int) $stmt->fetchColumn();
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
