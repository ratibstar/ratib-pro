<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;
use Rateb\App\Services\ApprovalWorkflowService;

/**
 * Phase 20B — read-only Approval master-data deltas + static catalogs.
 * Gated by offline.approval.masterdata (requires offline.enabled + offline.approval).
 */
final class ApprovalOfflineMasterDataDirectoryService
{
    /** @var array<string, array{table: string, select: string, branch_scoped: bool}|array{static: true}> */
    private const ENTITIES = [
        'approval_template_directory' => [
            'table' => 'rateb_eap_templates',
            'select' => 'id, company_id, branch_id, code, name, name_ar, module_key, status',
            'branch_scoped' => true,
        ],
        'approval_chain_directory' => [
            'table' => 'rateb_eap_chains',
            'select' => 'id, company_id, branch_id, template_id, code, name, status',
            'branch_scoped' => true,
        ],
        'approval_stage_directory' => [
            'table' => 'rateb_eap_stages',
            'select' => 'id, company_id, branch_id, template_id, code, name, sort_order, approver_role, min_approvals, sla_hours, status',
            'branch_scoped' => true,
        ],
        'approval_rule_directory' => [
            'table' => 'rateb_eap_rules',
            'select' => 'id, company_id, branch_id, template_id, code, name, rule_type, priority, status',
            'branch_scoped' => true,
        ],
        'approval_delegation_directory' => [
            'table' => 'rateb_eap_delegations',
            'select' => 'id, company_id, branch_id, request_id, from_user_id, to_user_id, starts_at, ends_at, status',
            'branch_scoped' => true,
        ],
        'approval_status_directory' => ['static' => true],
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

        if (!$this->flags()->isApprovalMasterDataEnabled()) {
            return [
                'entity_type' => $entity,
                'items' => [],
                'cursor_token' => $cursorToken,
                'stub' => true,
                'disabled' => true,
            ];
        }

        $meta = self::ENTITIES[$entity];
        if (!empty($meta['static'])) {
            return $this->pullStatic($entity);
        }

        $table = (string) $meta['table'];
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

        $select = (string) $meta['select'];
        if ($hasUpdated && !str_contains($select, 'updated_at')) {
            $select .= ', updated_at';
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $table . ' WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($hasDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($entity === 'approval_delegation_directory') {
            $sql .= ' AND status = \'active\'';
        }
        if (!empty($meta['branch_scoped']) && $hasBranch && $branchId !== null && $branchId > 0) {
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

    /**
     * @return array<string, mixed>
     */
    private function pullStatic(string $entity): array
    {
        $items = match ($entity) {
            'approval_status_directory' => (static function (): array {
                $out = [];
                foreach (ApprovalWorkflowService::statuses() as $i => $code) {
                    $out[] = [
                        'id' => $i + 1,
                        'code' => $code,
                        'name' => $code,
                        'active' => true,
                    ];
                }

                return $out;
            })(),
            default => [],
        };

        return [
            'entity_type' => $entity,
            'items' => $items,
            'cursor_token' => null,
            'has_more' => false,
            'stub' => false,
            'read_only' => true,
            'static_catalog' => true,
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
