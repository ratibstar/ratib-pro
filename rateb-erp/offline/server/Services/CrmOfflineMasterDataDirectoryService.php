<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Phase 17B — read-only CRM master-data deltas.
 * Gated by offline.crm.masterdata (requires offline.enabled + offline.crm).
 */
final class CrmOfflineMasterDataDirectoryService
{
    /** @var array<string, array{table: string, select: string, branch_scoped: bool}> */
    private const ENTITIES = [
        'crm_lead_source_directory' => [
            'table' => 'rateb_crm_lead_sources',
            'select' => 'id, company_id, branch_id, code, name, name_ar, status',
            'branch_scoped' => true,
        ],
        'crm_pipeline_stage_directory' => [
            'table' => 'rateb_crm_pipeline_stages',
            'select' => 'id, company_id, pipeline_id, code, name, name_ar, sort_order, probability_percent, is_won, is_lost, status',
            'branch_scoped' => false,
        ],
        'crm_tag_directory' => [
            'table' => 'rateb_crm_tags',
            'select' => 'id, company_id, branch_id, code, name, name_ar, color, status',
            'branch_scoped' => true,
        ],
        'crm_company_directory' => [
            'table' => 'rateb_crm_companies',
            'select' => 'id, company_id, branch_id, customer_id, code, name, name_ar, phone, email, status',
            'branch_scoped' => true,
        ],
    ];

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

    /** @return list<string> */
    public static function entityNames(): array
    {
        return array_keys(self::ENTITIES);
    }

    public function supports(string $entity): bool
    {
        return isset(self::ENTITIES[$entity]);
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(
        string $entity,
        ?int $companyId = null,
        ?int $branchId = null,
        ?string $cursorToken = null,
        int $limit = 200
    ): array {
        if (!$this->supports($entity)) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => null,
                'error' => 'entity_not_allowed',
            ];
        }

        if (!$this->flags()->isCrmMasterDataEnabled()) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'stub' => true,
                'disabled' => true,
            ];
        }

        $meta = self::ENTITIES[$entity];
        $table = $meta['table'];
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => null,
                'error' => 'company_required',
            ];
        }

        if (!OfflineSchema::hasColumn($table, 'id')) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'migration_required' => true,
            ];
        }

        $safeLimit = max(1, min(500, $limit));
        [$afterId, $afterUpdated] = OfflineDeltaCursorCodec::parse($cursorToken);
        $hasUpdated = OfflineSchema::hasColumn($table, 'updated_at');
        $hasDeleted = OfflineSchema::hasColumn($table, 'deleted_at');
        $hasBranch = OfflineSchema::hasColumn($table, 'branch_id');

        $select = $meta['select'];
        if ($hasUpdated && !str_contains($select, 'updated_at')) {
            $select .= ', updated_at';
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $table . ' WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($hasDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($meta['branch_scoped'] && $hasBranch && $branchId !== null && $branchId > 0) {
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
        $sql .= $hasUpdated
            ? ' ORDER BY updated_at ASC, id ASC LIMIT ' . $safeLimit
            : ' ORDER BY id ASC LIMIT ' . $safeLimit;

        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? 'active'));
            $active = !in_array($status, ['inactive', 'archived', 'cancelled'], true);
            $item = $row;
            $item['id'] = (int) ($row['id'] ?? 0);
            $item['company_id'] = (int) ($row['company_id'] ?? 0);
            $item['active'] = $active;
            $item['deleted'] = !$active;
            $item['updated_at'] = $row['updated_at'] ?? null;
            $item['version'] = max(1, (int) ($row['id'] ?? 1));
            $items[] = $item;
        }

        $nextCursor = $cursorToken;
        if ($items !== []) {
            $last = $items[count($items) - 1];
            $nextCursor = OfflineDeltaCursorCodec::encode(
                (int) $last['id'],
                (string) ($last['updated_at'] ?? '')
            );
            $this->persistCursor($entity, $companyId, $branchId, $nextCursor);
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

    private function persistCursor(string $entity, int $companyId, ?int $branchId, string $token): void
    {
        if (!OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id')) {
            return;
        }
        $params = ['cid' => $companyId, 'et' => substr($entity, 0, 64)];
        $sql = 'SELECT id FROM rateb_offline_entity_cursors WHERE company_id = :cid AND entity_type = :et';
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
            'entity_type' => substr($entity, 0, 64),
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
