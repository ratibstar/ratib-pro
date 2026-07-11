<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Additive inventory catalog delta pull for offline clients.
 * Read-only — does not modify inventory rows or existing APIs.
 */
final class InventoryOfflineCatalogService
{
    private const ENTITY = 'inventory_catalog';

    private ?OfflineEntityCursor $cursors = null;
    private ?OfflineFeatureFlagService $flags = null;

    private function cursors(): OfflineEntityCursor
    {
        return $this->cursors ??= new OfflineEntityCursor();
    }

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_inventory', 'id');
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null, int $limit = 200): array
    {
        if (!$this->flags()->enabled('offline.inventory.movements')) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => $cursorToken,
                'stub' => true,
                'disabled' => true,
            ];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => null,
                'error' => 'company_required',
            ];
        }

        if (!$this->isAvailable()) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => $cursorToken,
                'migration_required' => true,
            ];
        }

        $safeLimit = max(1, min(500, $limit));
        [$afterId, $afterUpdated] = $this->parseCursor($cursorToken);

        $sql = 'SELECT id, company_id, branch_id, warehouse_id, item_code, item_name, sku, barcode,
                       quantity, unit, unit_cost, reorder_level, min_stock, max_stock, status,
                       updated_at, created_at
                FROM rateb_inventory
                WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        if ($branchId !== null && $branchId > 0 && Database::liveTableHasColumn('rateb_inventory', 'branch_id')) {
            $sql .= ' AND (branch_id = :bid OR branch_id IS NULL)';
            $params['bid'] = $branchId;
        }

        if ($afterId > 0) {
            if ($afterUpdated !== '') {
                $sql .= ' AND (updated_at > :u OR (updated_at = :u2 AND id > :aid))';
                $params['u'] = $afterUpdated;
                $params['u2'] = $afterUpdated;
                $params['aid'] = $afterId;
            } else {
                $sql .= ' AND id > :aid';
                $params['aid'] = $afterId;
            }
        }

        $sql .= ' ORDER BY updated_at ASC, id ASC LIMIT ' . $safeLimit;

        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'company_id' => (int) ($row['company_id'] ?? 0),
                'branch_id' => isset($row['branch_id']) ? (int) $row['branch_id'] : null,
                'warehouse_id' => isset($row['warehouse_id']) ? (int) $row['warehouse_id'] : null,
                'item_code' => (string) ($row['item_code'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'barcode' => (string) ($row['barcode'] ?? ''),
                'quantity' => (float) ($row['quantity'] ?? 0),
                'unit' => (string) ($row['unit'] ?? ''),
                'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                'reorder_level' => (float) ($row['reorder_level'] ?? 0),
                'min_stock' => (float) ($row['min_stock'] ?? 0),
                'max_stock' => (float) ($row['max_stock'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'updated_at' => $row['updated_at'] ?? null,
                'version' => max(1, (int) ($row['id'] ?? 1)),
            ];
        }, $rows);

        $nextCursor = $cursorToken;
        if ($items !== []) {
            $last = $items[count($items) - 1];
            $nextCursor = $this->encodeCursor((int) $last['id'], (string) ($last['updated_at'] ?? ''));
            $this->persistCursor($companyId, $branchId, $nextCursor);
        }

        return [
            'entity_type' => self::ENTITY,
            'items' => $items,
            'cursor_token' => $nextCursor,
            'has_more' => count($items) >= $safeLimit,
            'stub' => false,
        ];
    }

    private function persistCursor(int $companyId, ?int $branchId, string $token): void
    {
        if (!Database::liveTableHasColumn('rateb_offline_entity_cursors', 'id')) {
            return;
        }
        $params = [
            'cid' => $companyId,
            'et' => self::ENTITY,
        ];
        $sql = 'SELECT id FROM rateb_offline_entity_cursors
                WHERE company_id = :cid AND entity_type = :et';
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';
        $existing = $this->cursors()->queryOne($sql, $params);
        if ($existing !== null) {
            $this->cursors()->update((int) $existing['id'], [
                'cursor_token' => substr($token, 0, 128),
            ]);
            return;
        }
        $this->cursors()->create([
            'company_id' => $companyId,
            'branch_id' => ($branchId !== null && $branchId > 0) ? $branchId : null,
            'entity_type' => self::ENTITY,
            'cursor_token' => substr($token, 0, 128),
        ]);
    }

    /** @return array{0: int, 1: string} */
    private function parseCursor(?string $token): array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return [0, ''];
        }
        if (str_contains($token, '|')) {
            [$updated, $id] = explode('|', $token, 2);

            return [max(0, (int) $id), trim($updated)];
        }

        return [max(0, (int) $token), ''];
    }

    private function encodeCursor(int $id, string $updatedAt): string
    {
        if ($updatedAt !== '') {
            return $updatedAt . '|' . $id;
        }

        return (string) $id;
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
