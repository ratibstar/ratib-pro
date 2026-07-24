<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;

/**
 * Shared order lookup by (company_id, idempotency_key).
 * Used by PosCheckoutService and PosSyncAcceptanceReconcileService.
 */
class PosOrderIdempotencyLookup
{
    /**
     * @return array<string, mixed>|null Completed order row, or null.
     * @throws \RuntimeException when order is still processing
     */
    public function findCompleted(int $companyId, string $key, bool $forUpdate = false): ?array
    {
        $row = $this->findRaw($companyId, $key, $forUpdate);
        if ($row === null) {
            return null;
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === 'completed') {
            return $row;
        }
        if ($status === 'processing') {
            throw new \RuntimeException(__('pos_checkout_in_progress'));
        }

        return null;
    }

    /** @return array<string, mixed>|null Any order row for the key. */
    public function findRaw(int $companyId, string $key, bool $forUpdate = false): ?array
    {
        $key = trim($key);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $sql = 'SELECT * FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k LIMIT 1';
        if ($forUpdate) {
            $sql = 'SELECT * FROM rateb_pos_orders WHERE company_id = :cid AND idempotency_key = :k LIMIT 1 FOR UPDATE';
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['cid' => $companyId, 'k' => $key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
