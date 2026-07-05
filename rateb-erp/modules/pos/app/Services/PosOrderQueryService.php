<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;

/** Shared order lookups for returns, exchanges, and quotes. */
final class PosOrderQueryService
{
    /** @return array<string, mixed>|null */
    public function findOrder(int $orderId, int $companyId, ?int $branchId = null): ?array
    {
        if ($orderId < 1 || $companyId < 1) {
            return null;
        }
        TenantContext::setCompanyId($companyId);
        $order = (new PosOrder())->find($orderId);
        if (!$order || (int) ($order['company_id'] ?? 0) !== $companyId) {
            return null;
        }
        if ($branchId !== null && $branchId > 0 && (int) ($order['branch_id'] ?? 0) !== $branchId) {
            return null;
        }
        return $order;
    }

    /** @return array<int, array<string, mixed>> */
    public function orderLines(int $orderId, int $companyId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_order_lines WHERE order_id = :oid AND company_id = :cid ORDER BY line_no'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function returnedQuantityForLine(int $originalLineId, int $companyId): float
    {
        if ($originalLineId < 1 || $companyId < 1) {
            return 0.0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(l.quantity), 0) FROM rateb_pos_order_lines l
             INNER JOIN rateb_pos_orders o ON o.id = l.order_id
             WHERE l.original_line_id = :lid AND l.company_id = :cid
               AND l.line_kind = :rk AND o.status = :st
               AND o.order_type IN (\'return\', \'exchange\')'
        );
        $stmt->execute([
            'lid' => $originalLineId,
            'cid' => $companyId,
            'rk' => 'return',
            'st' => 'completed',
        ]);
        return (float) $stmt->fetchColumn();
    }

    public function returnedTotalForOrder(int $originalOrderId, int $companyId, ?int $excludeOrderId = null): float
    {
        if ($originalOrderId < 1 || $companyId < 1) {
            return 0.0;
        }
        $db = Database::connection();
        $sql = 'SELECT COALESCE(SUM(l.line_total), 0) FROM rateb_pos_order_lines l
             INNER JOIN rateb_pos_orders o ON o.id = l.order_id
             WHERE o.original_order_id = :oid AND o.company_id = :cid
               AND o.status = :st AND l.line_kind = :rk
               AND o.order_type IN (\'return\', \'exchange\')';
        $params = [
            'oid' => $originalOrderId,
            'cid' => $companyId,
            'st' => 'completed',
            'rk' => 'return',
        ];
        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $sql .= ' AND o.id <> :ex';
            $params['ex'] = $excludeOrderId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public function isOrderFullyReturned(array $originalOrder, int $companyId, float $additionalReturnTotal = 0.0, ?int $excludeOrderId = null): bool
    {
        $originalTotal = (float) ($originalOrder['total'] ?? 0);
        if ($originalTotal <= 0) {
            return false;
        }
        $returned = $this->returnedTotalForOrder((int) ($originalOrder['id'] ?? 0), $companyId, $excludeOrderId);
        return ($returned + $additionalReturnTotal) >= ($originalTotal - 0.02);
    }

    /** @return array<int, array<string, mixed>> */
    public function orderPayments(int $orderId, int $companyId): array
    {
        if ($orderId < 1 || $companyId < 1) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_payments WHERE order_id = :oid AND company_id = :cid ORDER BY id'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function orderRefunds(int $orderId, int $companyId): array
    {
        if ($orderId < 1 || $companyId < 1) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_refunds WHERE order_id = :oid AND company_id = :cid ORDER BY id'
        );
        $stmt->execute(['oid' => $orderId, 'cid' => $companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lock original sale order for return/exchange — serializes concurrent returns on same order.
     *
     * @return array<string, mixed>
     */
    public function lockOriginalOrderForReturn(int $originalOrderId, int $companyId, ?int $branchId = null): array
    {
        if ($originalOrderId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('pos_return_requires_transaction'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_orders WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $originalOrderId, 'cid' => $companyId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            throw new \RuntimeException(__('pos_return_invalid_order'));
        }
        if ($branchId !== null && $branchId > 0 && (int) ($order['branch_id'] ?? 0) !== $branchId) {
            throw new \RuntimeException(__('pos_return_invalid_order'));
        }
        if ((string) ($order['order_type'] ?? '') !== 'sale' || (string) ($order['status'] ?? '') !== 'completed') {
            throw new \RuntimeException(__('pos_return_invalid_order'));
        }
        return $order;
    }

    /**
     * @param array<int, array<string, mixed>> $returnLines
     * @param array<int, array<string, mixed>> $linesById
     */
    public function assertReturnLineQuantities(array $returnLines, array $linesById, int $companyId): void
    {
        foreach ($returnLines as $req) {
            if (!is_array($req)) {
                continue;
            }
            $origLineId = (int) ($req['original_line_id'] ?? 0);
            $qty = (float) ($req['quantity'] ?? 0);
            if ($origLineId < 1 || $qty <= 0 || !isset($linesById[$origLineId])) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $orig = $linesById[$origLineId];
            $returnable = (float) ($orig['quantity'] ?? 0) - $this->returnedQuantityForLine($origLineId, $companyId);
            if ($qty > $returnable + 0.0001) {
                throw new \RuntimeException(__('pos_return_qty_exceeded'));
            }
        }
    }

    /** Lock original sale line before batch restoration allocation. */
    public function lockOriginalLineForReturn(int $originalLineId, int $companyId): void
    {
        if ($originalLineId < 1 || $companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!Database::connection()->inTransaction()) {
            throw new \RuntimeException(__('pos_return_requires_transaction'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_pos_order_lines WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $originalLineId, 'cid' => $companyId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException(__('invalid_request'));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function returnableLines(int $orderId, int $companyId, ?int $branchId = null): array
    {
        $order = $this->findOrder($orderId, $companyId, $branchId);
        if (!$order || (string) ($order['order_type'] ?? '') !== 'sale' || (string) ($order['status'] ?? '') !== 'completed') {
            return [];
        }
        $out = [];
        foreach ($this->orderLines($orderId, $companyId) as $line) {
            if ((string) ($line['line_kind'] ?? 'sale') !== 'sale') {
                continue;
            }
            $lineId = (int) ($line['id'] ?? 0);
            $sold = (float) ($line['quantity'] ?? 0);
            $returned = $this->returnedQuantityForLine($lineId, $companyId);
            $remaining = max(0, round($sold - $returned, 3));
            if ($remaining <= 0) {
                continue;
            }
            $out[] = array_merge($line, [
                'returnable_qty' => $remaining,
                'returned_qty' => $returned,
            ]);
        }
        return $out;
    }

    /**
     * Search completed sale orders eligible for return/exchange (branch-scoped).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchReturnableOrders(int $companyId, int $branchId, string $query, int $limit = 20): array
    {
        if ($companyId < 1 || $branchId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        $limit = max(1, min(50, $limit));
        $q = trim($query);
        $db = Database::connection();
        $sql = 'SELECT id, order_no, total, completed_at, customer_id
                FROM rateb_pos_orders
                WHERE company_id = :cid AND branch_id = :bid
                  AND order_type = :ot AND status = :st';
        $params = [
            'cid' => $companyId,
            'bid' => $branchId,
            'ot' => 'sale',
            'st' => 'completed',
        ];
        if ($q !== '') {
            if (ctype_digit($q)) {
                $sql .= ' AND (id = :oid OR order_no LIKE :ono)';
                $params['oid'] = (int) $q;
                $params['ono'] = '%' . $q . '%';
            } else {
                $sql .= ' AND order_no LIKE :ono';
                $params['ono'] = '%' . $q . '%';
            }
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Parse stored batch allocations from an order line (JSON or legacy batch_id).
     *
     * @param array<string, mixed> $line
     * @return array<int, array<string, mixed>>
     */
    public function parseLineBatchAllocations(array $line): array
    {
        $json = $line['batch_allocations_json'] ?? null;
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && $decoded !== []) {
                return $this->normalizeAllocations($decoded);
            }
        }
        if (is_array($json) && $json !== []) {
            return $this->normalizeAllocations($json);
        }
        $batchId = (int) ($line['batch_id'] ?? 0);
        $qty = (float) ($line['quantity'] ?? 0);
        if ($batchId > 0 && $qty > 0) {
            return [['batch_id' => $batchId, 'quantity' => round($qty, 3)]];
        }
        return [];
    }

    /**
     * Compute batch restorations for a return — LIFO within remaining allocations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function computeReturnBatchAllocations(int $originalLineId, float $returnQty, int $companyId): array
    {
        if ($originalLineId < 1 || $returnQty <= 0 || $companyId < 1) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_pos_order_lines WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $originalLineId, 'cid' => $companyId]);
        $line = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$line) {
            return [];
        }

        $this->lockOriginalLineForReturn($originalLineId, $companyId);

        $soldAllocs = $this->parseLineBatchAllocations($line);
        if ($soldAllocs === [] && $this->tableExists('rateb_pos_batch_ledger')) {
            $soldAllocs = $this->batchOutAllocationsFromLedger($originalLineId, $companyId);
        }
        if ($soldAllocs === []) {
            return [];
        }

        $returnedByBatch = $this->batchReturnedByBatch($originalLineId, $companyId);
        $remaining = [];
        foreach ($soldAllocs as $alloc) {
            $batchId = (int) ($alloc['batch_id'] ?? 0);
            $outQty = (float) ($alloc['quantity'] ?? 0);
            if ($batchId < 1 || $outQty <= 0) {
                continue;
            }
            $already = (float) ($returnedByBatch[$batchId] ?? 0);
            $rem = max(0, round($outQty - $already, 3));
            if ($rem > 0) {
                $remaining[] = [
                    'batch_id' => $batchId,
                    'batch_no' => (string) ($alloc['batch_no'] ?? ''),
                    'quantity' => $rem,
                    'expiry_date' => (string) ($alloc['expiry_date'] ?? ''),
                    'unit_cost' => (float) ($alloc['unit_cost'] ?? 0),
                ];
            }
        }
        if ($remaining === []) {
            return [];
        }

        $restorations = [];
        $need = round($returnQty, 3);
        for ($i = count($remaining) - 1; $i >= 0 && $need > 0.0001; $i--) {
            $batch = $remaining[$i];
            $take = min((float) ($batch['quantity'] ?? 0), $need);
            if ($take <= 0) {
                continue;
            }
            $restorations[] = [
                'batch_id' => (int) ($batch['batch_id'] ?? 0),
                'batch_no' => (string) ($batch['batch_no'] ?? ''),
                'quantity' => round($take, 3),
                'expiry_date' => (string) ($batch['expiry_date'] ?? ''),
                'unit_cost' => (float) ($batch['unit_cost'] ?? 0),
            ];
            $need = round($need - $take, 3);
        }
        if ($need > 0.0001) {
            throw new \RuntimeException(__('pos_return_batch_exceeded'));
        }
        return $restorations;
    }

    /** @return array<int, array<string, mixed>> */
    private function batchOutAllocationsFromLedger(int $orderLineId, int $companyId): array
    {
        if (!$this->tableExists('rateb_pos_batch_ledger')) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT batch_id, SUM(quantity) AS qty
             FROM rateb_pos_batch_ledger
             WHERE company_id = :cid AND order_line_id = :lid AND direction = :dir
             GROUP BY batch_id'
        );
        $stmt->execute(['cid' => $companyId, 'lid' => $orderLineId, 'dir' => 'out']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $qty = (float) ($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $out[] = ['batch_id' => (int) ($row['batch_id'] ?? 0), 'quantity' => round($qty, 3)];
        }
        return $out;
    }

    /** @return array<int, float> batch_id => returned qty */
    private function batchReturnedByBatch(int $originalLineId, int $companyId): array
    {
        if (!$this->tableExists('rateb_pos_batch_ledger')) {
            return [];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT batch_id, SUM(quantity) AS qty
             FROM rateb_pos_batch_ledger
             WHERE company_id = :cid AND original_line_id = :lid AND direction = :dir
             GROUP BY batch_id'
        );
        $stmt->execute(['cid' => $companyId, 'lid' => $originalLineId, 'dir' => 'in']);
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $bid = (int) ($row['batch_id'] ?? 0);
            if ($bid > 0) {
                $map[$bid] = (float) ($row['qty'] ?? 0);
            }
        }
        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $allocations
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAllocations(array $allocations): array
    {
        $out = [];
        foreach ($allocations as $alloc) {
            if (!is_array($alloc)) {
                continue;
            }
            $batchId = (int) ($alloc['batch_id'] ?? 0);
            $qty = (float) ($alloc['quantity'] ?? 0);
            if ($batchId < 1 || $qty <= 0) {
                continue;
            }
            $out[] = [
                'batch_id' => $batchId,
                'batch_no' => (string) ($alloc['batch_no'] ?? ''),
                'quantity' => round($qty, 3),
                'expiry_date' => (string) ($alloc['expiry_date'] ?? ''),
                'unit_cost' => (float) ($alloc['unit_cost'] ?? 0),
            ];
        }
        return $out;
    }

    private function tableExists(string $table): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
