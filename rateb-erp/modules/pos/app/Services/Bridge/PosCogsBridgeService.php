<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Services\PosOrderQueryService;

/**
 * Batch-aware COGS — weighted cost from FEFO allocations recorded at checkout.
 * Bridge-only; no duplicated inventory logic.
 */
final class PosCogsBridgeService
{
    public function __construct(
        private PosOrderQueryService $queries = new PosOrderQueryService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $orderLines
     */
    public function sumForOrderLines(array $orderLines, int $companyId): float
    {
        if ($orderLines === [] || $companyId < 1) {
            return 0.0;
        }
        TenantContext::setCompanyId($companyId);
        $total = 0.0;
        $invCosts = $this->inventoryUnitCosts($orderLines, $companyId);
        foreach ($orderLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $total += $this->lineCogs($line, $companyId, $invCosts);
        }
        return round($total, 2);
    }

    /**
     * Attach unit_cost to each batch allocation (snapshot at consumption lock time).
     *
     * @param array<int, array<string, mixed>> $allocations
     * @return array<int, array<string, mixed>>
     */
    public function enrichBatchAllocations(int $inventoryId, int $companyId, array $allocations): array
    {
        if ($allocations === [] || $inventoryId < 1 || $companyId < 1) {
            return $allocations;
        }
        $fallback = $this->inventoryUnitCost($inventoryId, $companyId);
        $batchCosts = $this->batchUnitCosts($allocations, $companyId);
        $out = [];
        foreach ($allocations as $alloc) {
            if (!is_array($alloc)) {
                continue;
            }
            $batchId = (int) ($alloc['batch_id'] ?? 0);
            $existing = (float) ($alloc['unit_cost'] ?? 0);
            $unitCost = $existing > 0
                ? $existing
                : ($batchCosts[$batchId] ?? $fallback);
            $out[] = array_merge($alloc, ['unit_cost' => round(max(0, $unitCost), 4)]);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $line
     * @param array<int, float> $invCosts
     */
    private function lineCogs(array $line, int $companyId, array $invCosts): float
    {
        $invId = (int) ($line['inventory_id'] ?? $line['product_id'] ?? 0);
        $lineQty = (float) ($line['quantity'] ?? 0);
        if ($invId < 1 || $lineQty <= 0) {
            return 0.0;
        }

        $allocs = $this->allocationsForLine($line);
        if ($allocs !== []) {
            $sumQty = 0.0;
            $cost = 0.0;
            foreach ($allocs as $alloc) {
                $qty = (float) ($alloc['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $unitCost = $this->allocationUnitCost($alloc, $invId, $companyId, $invCosts);
                $cost += round($qty * $unitCost, 4);
                $sumQty += $qty;
            }
            if ($sumQty + 0.0001 < $lineQty) {
                $remainder = max(0, round($lineQty - $sumQty, 3));
                $fallback = (float) ($invCosts[$invId] ?? $this->inventoryUnitCost($invId, $companyId));
                $cost += round($remainder * $fallback, 4);
            }
            return round($cost, 2);
        }

        $unitCost = (float) ($invCosts[$invId] ?? $this->inventoryUnitCost($invId, $companyId));
        return round($lineQty * $unitCost, 2);
    }

    /** @param array<string, mixed> $line @return array<int, array<string, mixed>> */
    private function allocationsForLine(array $line): array
    {
        if (is_array($line['batch_restorations'] ?? null) && ($line['batch_restorations'] ?? []) !== []) {
            return $this->queries->parseLineBatchAllocations(['batch_allocations_json' => json_encode($line['batch_restorations'])]);
        }
        if (is_array($line['batch_allocations'] ?? null) && ($line['batch_allocations'] ?? []) !== []) {
            return $this->queries->parseLineBatchAllocations(['batch_allocations_json' => json_encode($line['batch_allocations'])]);
        }
        return $this->queries->parseLineBatchAllocations($line);
    }

    /**
     * @param array<string, mixed> $alloc
     * @param array<int, float> $invCosts
     */
    private function allocationUnitCost(array $alloc, int $inventoryId, int $companyId, array $invCosts): float
    {
        $snap = (float) ($alloc['unit_cost'] ?? 0);
        if ($snap > 0) {
            return $snap;
        }
        $batchId = (int) ($alloc['batch_id'] ?? 0);
        if ($batchId > 0) {
            $fromBatch = $this->batchUnitCosts([$alloc], $companyId)[$batchId] ?? 0.0;
            if ($fromBatch > 0) {
                return $fromBatch;
            }
        }
        return (float) ($invCosts[$inventoryId] ?? $this->inventoryUnitCost($inventoryId, $companyId));
    }

    /**
     * @param array<int, array<string, mixed>> $orderLines
     * @return array<int, float>
     */
    private function inventoryUnitCosts(array $orderLines, int $companyId): array
    {
        $ids = [];
        foreach ($orderLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $id = (int) ($line['inventory_id'] ?? $line['product_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        $db = Database::connection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$companyId], array_map('intval', array_keys($ids)));
        $stmt = $db->prepare(
            "SELECT id, unit_cost FROM rateb_inventory WHERE company_id = ? AND id IN ({$placeholders})"
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) ($row['id'] ?? 0)] = (float) ($row['unit_cost'] ?? 0);
        }
        return $out;
    }

    private function inventoryUnitCost(int $inventoryId, int $companyId): float
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT unit_cost FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $inventoryId, 'cid' => $companyId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float) $val : 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $allocations
     * @return array<int, float>
     */
    private function batchUnitCosts(array $allocations, int $companyId): array
    {
        if (!$this->batchHasUnitCostColumn()) {
            return [];
        }
        $batchIds = [];
        foreach ($allocations as $alloc) {
            $id = (int) ($alloc['batch_id'] ?? 0);
            if ($id > 0) {
                $batchIds[$id] = true;
            }
        }
        if ($batchIds === []) {
            return [];
        }
        $db = Database::connection();
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $params = array_merge([$companyId], array_map('intval', array_keys($batchIds)));
        $stmt = $db->prepare(
            "SELECT id, unit_cost FROM rateb_inventory_batches WHERE company_id = ? AND id IN ({$placeholders})"
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $cost = (float) ($row['unit_cost'] ?? 0);
            if ($cost > 0) {
                $out[(int) ($row['id'] ?? 0)] = $cost;
            }
        }
        return $out;
    }

    private function batchHasUnitCostColumn(): bool
    {
        return Database::liveTableHasColumn('rateb_inventory_batches', 'unit_cost');
    }
}
