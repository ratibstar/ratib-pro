<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Additive supplier directory delta pull for offline Procurement clients.
 * Read-only — does not modify supplier rows or existing APIs.
 * Excludes payment / banking fields from the cache payload.
 */
final class ProcurementOfflineSupplierDirectoryService
{
    private const ENTITY = 'supplier_directory';

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
        return OfflineSchema::hasColumn('rateb_suppliers', 'id');
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null, int $limit = 200): array
    {
        if (!$this->flags()->enabled('offline.procurement')) {
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

        $hasUpdated = OfflineSchema::hasColumn('rateb_suppliers', 'updated_at');
        $sql = 'SELECT id, company_id, branch_id, name, code, email, phone, address,
                       rating, classification_id, status';
        if ($hasUpdated) {
            $sql .= ', updated_at, created_at';
        } else {
            $sql .= ', created_at';
        }
        $sql .= ' FROM rateb_suppliers WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        if ($branchId !== null && $branchId > 0 && OfflineSchema::hasColumn('rateb_suppliers', 'branch_id')) {
            $sql .= ' AND (branch_id = :bid OR branch_id IS NULL)';
            $params['bid'] = $branchId;
        }

        if ($afterId > 0) {
            if ($hasUpdated && $afterUpdated !== '') {
                $sql .= ' AND (updated_at > :u OR (updated_at = :u2 AND id > :aid))';
                $params['u'] = $afterUpdated;
                $params['u2'] = $afterUpdated;
                $params['aid'] = $afterId;
            } else {
                $sql .= ' AND id > :aid';
                $params['aid'] = $afterId;
            }
        }

        if ($hasUpdated) {
            $sql .= ' ORDER BY updated_at ASC, id ASC LIMIT ' . $safeLimit;
        } else {
            $sql .= ' ORDER BY id ASC LIMIT ' . $safeLimit;
        }

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
                'name' => (string) ($row['name'] ?? ''),
                'code' => (string) ($row['code'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'rating' => isset($row['rating']) ? (float) $row['rating'] : null,
                'classification_id' => isset($row['classification_id']) ? (int) $row['classification_id'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
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
        if (!OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id')) {
            return;
        }
        $params = ['cid' => $companyId, 'et' => self::ENTITY];
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
        if ($token === null || $token === '') {
            return [0, ''];
        }
        $parts = explode('|', $token, 2);

        return [(int) ($parts[0] ?? 0), (string) ($parts[1] ?? '')];
    }

    private function encodeCursor(int $id, string $updatedAt): string
    {
        return $id . '|' . substr($updatedAt, 0, 32);
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
