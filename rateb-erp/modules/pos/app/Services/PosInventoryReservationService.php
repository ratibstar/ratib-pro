<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;

/**
 * Soft POS cart reservations — prevents concurrent oversell across terminals (no stock posting).
 */
final class PosInventoryReservationService
{
    private const TTL_MINUTES = 45;

    public function reservedQuantity(int $companyId, int $inventoryId, ?int $excludeSessionId = null): float
    {
        if ($companyId < 1 || $inventoryId < 1 || !$this->tableExists('rateb_pos_inventory_reservations')) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(quantity), 0) FROM rateb_pos_inventory_reservations
                WHERE company_id = :cid AND inventory_id = :iid AND status = :st AND expires_at > NOW()';
        $params = ['cid' => $companyId, 'iid' => $inventoryId, 'st' => 'active'];
        if ($excludeSessionId !== null && $excludeSessionId > 0) {
            $sql .= ' AND (session_id IS NULL OR session_id != :sid)';
            $params['sid'] = $excludeSessionId;
        }
        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    /**
     * @param list<int> $inventoryIds
     * @return array<int, float>
     */
    public function reservedQuantitiesForIds(int $companyId, array $inventoryIds, ?int $excludeSessionId = null): array
    {
        if ($companyId < 1 || $inventoryIds === [] || !$this->tableExists('rateb_pos_inventory_reservations')) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $inventoryIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT inventory_id, COALESCE(SUM(quantity), 0) AS qty
                FROM rateb_pos_inventory_reservations
                WHERE company_id = ? AND inventory_id IN (' . $placeholders . ')
                  AND status = ? AND expires_at > NOW()';
        $params = array_merge([$companyId], $ids, ['active']);
        if ($excludeSessionId !== null && $excludeSessionId > 0) {
            $sql .= ' AND (session_id IS NULL OR session_id != ?)';
            $params[] = $excludeSessionId;
        }
        $sql .= ' GROUP BY inventory_id';

        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $inventoryId = (int) ($row['inventory_id'] ?? 0);
            if ($inventoryId > 0) {
                $map[$inventoryId] = (float) ($row['qty'] ?? 0);
            }
        }

        return $map;
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function syncSessionCart(
        int $companyId,
        int $branchId,
        int $sessionId,
        array $lines
    ): void {
        if ($companyId < 1 || $branchId < 1 || $sessionId < 1 || !$this->tableExists('rateb_pos_inventory_reservations')) {
            return;
        }
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->releaseSession($companyId, $sessionId, false);
            $expires = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);
            $insert = $db->prepare(
                'INSERT INTO rateb_pos_inventory_reservations
                 (company_id, branch_id, inventory_id, session_id, quantity, status, expires_at)
                 VALUES (:cid, :bid, :iid, :sid, :qty, :st, :exp)'
            );
            $aggregated = [];
            foreach ($lines as $line) {
                $invId = (int) ($line['product_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                if ($invId < 1 || $qty <= 0) {
                    continue;
                }
                $aggregated[$invId] = ($aggregated[$invId] ?? 0) + $qty;
            }
            foreach ($aggregated as $invId => $qty) {
                $insert->execute([
                    'cid' => $companyId,
                    'bid' => $branchId,
                    'iid' => $invId,
                    'sid' => $sessionId,
                    'qty' => round($qty, 3),
                    'st' => 'active',
                    'exp' => $expires,
                ]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function releaseSession(int $companyId, int $sessionId, bool $useTransaction = true): void
    {
        if ($companyId < 1 || $sessionId < 1 || !$this->tableExists('rateb_pos_inventory_reservations')) {
            return;
        }
        $db = Database::connection();
        if ($useTransaction) {
            $db->beginTransaction();
        }
        try {
            $db->prepare(
                'UPDATE rateb_pos_inventory_reservations SET status = :st
                 WHERE company_id = :cid AND session_id = :sid AND status = :active'
            )->execute([
                'st' => 'released',
                'cid' => $companyId,
                'sid' => $sessionId,
                'active' => 'active',
            ]);
            if ($useTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function expireStale(): int
    {
        if (!$this->tableExists('rateb_pos_inventory_reservations')) {
            return 0;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_pos_inventory_reservations SET status = :expired
             WHERE status = :active AND expires_at <= NOW()'
        );
        $stmt->execute(['expired' => 'expired', 'active' => 'active']);
        return $stmt->rowCount();
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
