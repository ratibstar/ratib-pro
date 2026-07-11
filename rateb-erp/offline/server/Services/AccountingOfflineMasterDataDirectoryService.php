<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Phase 16B — read-only Accounting master-data deltas.
 * Gated by offline.accounting.masterdata (requires offline.enabled + offline.accounting).
 */
final class AccountingOfflineMasterDataDirectoryService
{
    /** @var array<string, array{table: string, select: string, branch_scoped: bool}> */
    private const ENTITIES = [
        'chart_of_accounts_directory' => [
            'table' => 'rateb_chart_of_accounts',
            'select' => 'id, company_id, code, name, name_ar, account_type, parent_id, is_active',
            'branch_scoped' => false,
        ],
        'accounting_currency_directory' => [
            'table' => 'rateb_accounting_currencies',
            'select' => 'id, company_id, branch_id, code, name, name_ar, symbol, decimal_places, is_base, status',
            'branch_scoped' => true,
        ],
        'accounting_exchange_rate_directory' => [
            'table' => 'rateb_accounting_exchange_rates',
            'select' => 'id, company_id, branch_id, from_currency, to_currency, rate, rate_date, source, status',
            'branch_scoped' => true,
        ],
        'accounting_tax_code_directory' => [
            'table' => 'rateb_accounting_tax_codes',
            'select' => 'id, company_id, branch_id, code, name, name_ar, rate_percent, tax_type, recoverable, account_id, status',
            'branch_scoped' => true,
        ],
        'accounting_cost_center_directory' => [
            'table' => 'rateb_cost_centers',
            'select' => 'id, company_id, code, name, name_ar, parent_id, is_active',
            'branch_scoped' => false,
        ],
        'accounting_profit_center_directory' => [
            'table' => 'rateb_accounting_profit_centers',
            'select' => 'id, company_id, branch_id, code, name, name_ar, parent_id, status',
            'branch_scoped' => true,
        ],
        'accounting_fiscal_period_directory' => [
            'table' => 'rateb_fiscal_periods',
            'select' => 'id, company_id, name, start_date, end_date, status',
            'branch_scoped' => false,
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

        if (!$this->flags()->isAccountingMasterDataEnabled()) {
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
        if (OfflineSchema::hasColumn($table, 'created_at') && !str_contains($select, 'created_at')) {
            $select .= ', created_at';
        }
        if ($entity === 'accounting_fiscal_period_directory'
            && OfflineSchema::hasColumn($table, 'locked')
            && !str_contains($select, 'locked')) {
            $select .= ', locked';
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
            $active = true;
            if (array_key_exists('is_active', $row)) {
                $active = (int) ($row['is_active'] ?? 0) === 1;
            } elseif (array_key_exists('status', $row)) {
                $status = strtolower((string) ($row['status'] ?? ''));
                $active = !in_array($status, ['inactive', 'closed', 'locked'], true);
            }
            $item = $row;
            $item['id'] = (int) ($row['id'] ?? 0);
            $item['company_id'] = (int) ($row['company_id'] ?? 0);
            if (isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
                $item['branch_id'] = (int) $row['branch_id'];
            }
            $item['active'] = $active;
            $item['deleted'] = !$active;
            $item['updated_at'] = $row['updated_at'] ?? ($row['created_at'] ?? null);
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
