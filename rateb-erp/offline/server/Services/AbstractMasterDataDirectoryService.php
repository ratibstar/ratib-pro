<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Phase 13 — Shared read-only master-data delta pull helpers.
 */
abstract class AbstractMasterDataDirectoryService
{
    abstract protected function entityType(): string;

    abstract protected function table(): string;

    /** @return list<string> SELECT columns (without updated_at/created_at) */
    abstract protected function selectColumns(): array;

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    abstract protected function mapItem(array $row): array;

    protected function requiresUpdatedAt(): bool
    {
        return true;
    }

    protected function branchColumn(): ?string
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return OfflineSchema::hasColumn($this->table(), 'id');
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null, int $limit = 200): array
    {
        $entity = $this->entityType();
        if (!(new OfflineFeatureFlagService())->isMasterDataEnabled()) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'stub' => true,
                'disabled' => true,
            ];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => null,
                'error' => 'company_required',
            ];
        }

        if (!$this->isAvailable()) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'migration_required' => true,
            ];
        }

        $hasUpdated = OfflineSchema::hasColumn($this->table(), 'updated_at');
        if ($this->requiresUpdatedAt() && !$hasUpdated) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'migration_required' => true,
                'error' => 'updated_at_required',
            ];
        }

        $safeLimit = max(1, min(500, $limit));
        [$afterId, $afterUpdated] = OfflineDeltaCursorCodec::parse($cursorToken);

        $cols = $this->selectColumns();
        if ($hasUpdated) {
            $cols[] = 'updated_at';
        }
        if (OfflineSchema::hasColumn($this->table(), 'created_at')) {
            $cols[] = 'created_at';
        }
        $cols = array_values(array_unique($cols));

        $sql = 'SELECT ' . implode(', ', $cols)
            . ' FROM ' . $this->table()
            . ' WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        $branchCol = $this->branchColumn();
        if ($branchId !== null && $branchId > 0 && $branchCol !== null
            && OfflineSchema::hasColumn($this->table(), $branchCol)) {
            $sql .= ' AND (' . $branchCol . ' = :bid OR ' . $branchCol . ' IS NULL)';
            $params['bid'] = $branchId;
        }

        if ($afterId > 0) {
            if ($hasUpdated && $afterUpdated !== '' && $afterUpdated !== '0') {
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

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapItem($row);
        }

        $nextCursor = $cursorToken;
        if ($items !== []) {
            $last = $items[count($items) - 1];
            $nextCursor = OfflineDeltaCursorCodec::encode(
                (int) $last['id'],
                (string) ($last['updated_at'] ?? '')
            );
            $this->persistCursor($companyId, $branchId, $nextCursor);
        }

        return [
            'entity_type' => $entity,
            'items' => $items,
            'cursor_token' => $nextCursor,
            'has_more' => count($items) >= $safeLimit,
            'stub' => false,
            'read_only' => true,
        ];
    }

    private function persistCursor(int $companyId, ?int $branchId, string $token): void
    {
        if (!OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id')) {
            return;
        }
        $model = new OfflineEntityCursor();
        $params = ['cid' => $companyId, 'et' => $this->entityType()];
        $sql = 'SELECT id FROM rateb_offline_entity_cursors
                WHERE company_id = :cid AND entity_type = :et';
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';
        $existing = $model->queryOne($sql, $params);
        if ($existing !== null) {
            $model->update((int) $existing['id'], [
                'cursor_token' => substr($token, 0, 128),
            ]);
            return;
        }
        $model->create([
            'company_id' => $companyId,
            'branch_id' => ($branchId !== null && $branchId > 0) ? $branchId : null,
            'entity_type' => $this->entityType(),
            'cursor_token' => substr($token, 0, 128),
        ]);
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
