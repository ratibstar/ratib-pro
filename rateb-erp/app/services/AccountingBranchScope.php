<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\JournalEntry;

/** Applies branch isolation to accounting SQL (journal entries, lines, bank, cost centers). */
trait AccountingBranchScope
{
    private ?BranchIsolationService $accountingBranchIsolation = null;

    protected function tableColumnExists(string $table, string $column): bool
    {
        return \Rateb\App\Core\Database::liveTableHasColumn($table, $column);
    }

    protected function journalLineBranchColumnExists(): bool
    {
        static $cache = [];
        $db = \Rateb\App\Core\Database::resolvedDatabaseName();
        if (array_key_exists($db, $cache)) {
            return $cache[$db];
        }
        $cache[$db] = $this->tableColumnExists('rateb_journal_lines', 'branch_id');
        return $cache[$db];
    }

    protected function accountingBranch(): BranchIsolationService
    {
        return $this->accountingBranchIsolation ??= new BranchIsolationService();
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeJournalEntrySql(string $sql, array $params, string $alias = 'e', ?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $col = ($alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '') . 'branch_id';
            $key = '_acct_bf_single';
            if (preg_match('/\bWHERE\b/i', $sql)) {
                return [$sql . ' AND ' . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
            }
            return [$sql . ' WHERE ' . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
        }
        if (stripos($sql, 'rateb_journal_entries') === false && !preg_match('/\b' . preg_quote($alias, '/') . '\./i', $sql)) {
            return [$sql, $params];
        }
        if (!$this->tableColumnExists('rateb_journal_entries', 'branch_id')) {
            return [$sql, $params];
        }
        $safeAlias = preg_replace('/[^a-z_]/', '', $alias);
        if ($safeAlias !== '' && !preg_match('/\b' . preg_quote($safeAlias, '/') . '\b/i', $sql)) {
            return [$sql, $params];
        }
        return $this->accountingBranch()->appendFilter($sql, $params, $safeAlias, 'branch_id');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeJournalLineSql(string $sql, array $params, string $lineAlias = 'l', string $entryAlias = 'e', ?int $branchId = null): array
    {
        [$sql, $params] = $this->scopeJournalEntrySql($sql, $params, $entryAlias, $branchId);
        if ($branchId !== null && $branchId > 0) {
            $col = ($lineAlias !== '' ? preg_replace('/[^a-z_]/', '', $lineAlias) . '.' : '') . 'branch_id';
            $key = '_jl_bf_single';
            return [$sql . ' AND ' . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
        }
        if (stripos($sql, 'rateb_journal_lines') !== false && $this->journalLineBranchColumnExists()) {
            return $this->accountingBranch()->appendFilter($sql, $params, $lineAlias, 'branch_id');
        }
        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeBankAccountSql(string $sql, array $params, string $alias = 'ba', ?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $col = ($alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '') . 'branch_id';
            $key = '_ba_bf_single';
            return [$sql . (preg_match('/\bWHERE\b/i', $sql) ? ' AND ' : ' WHERE ') . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
        }
        if (stripos($sql, 'rateb_bank_accounts') === false || !$this->tableColumnExists('rateb_bank_accounts', 'branch_id')) {
            return [$sql, $params];
        }
        return $this->accountingBranch()->appendFilter($sql, $params, $alias, 'branch_id');
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeCostCenterSql(string $sql, array $params, string $alias = 'cc', ?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $col = ($alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '') . 'branch_id';
            $key = '_cc_bf_single';
            return [$sql . (preg_match('/\bWHERE\b/i', $sql) ? ' AND ' : ' WHERE ') . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
        }
        if (stripos($sql, 'rateb_cost_centers') === false || !$this->tableColumnExists('rateb_cost_centers', 'branch_id')) {
            return [$sql, $params];
        }
        return $this->accountingBranch()->appendFilter($sql, $params, $alias, 'branch_id');
    }

    protected function resolveJournalLineBranchId(int $entryId): int
    {
        if ($entryId < 1) {
            return function_exists('rateb_resolve_create_branch_id') ? rateb_resolve_create_branch_id() : 0;
        }
        $row = (new JournalEntry())->find($entryId);
        $bid = (int) ($row['branch_id'] ?? 0);
        if ($bid > 0) {
            return $bid;
        }
        return function_exists('rateb_resolve_create_branch_id') ? rateb_resolve_create_branch_id() : 0;
    }

    /** @param array<string, mixed> $params @return array<int, array<string, mixed>> */
    protected function journalScopedQuery(string $sql, array $params, string $entryAlias = 'e', ?int $branchId = null): array
    {
        [$sql, $params] = $this->scopeJournalLineSql($sql, $params, 'l', $entryAlias, $branchId);

        return $this->executeScopedSql($sql, $params);
    }

    /** @param array<string, mixed> $params @return array<string, mixed>|null */
    protected function journalScopedQueryOne(string $sql, array $params, string $entryAlias = 'e', ?int $branchId = null): ?array
    {
        [$sql, $params] = $this->scopeJournalEntrySql($sql, $params, $entryAlias, $branchId);

        return $this->executeScopedSqlOne($sql, $params);
    }

    /**
     * Resolve SQL alias for a table (FROM or JOIN). Empty when the table has no alias.
     */
    protected function sqlTableAlias(string $sql, string $table): string
    {
        $t = preg_quote($table, '/');
        static $keywords = [
            'where', 'join', 'left', 'right', 'inner', 'outer', 'cross', 'on', 'using',
            'group', 'order', 'limit', 'having', 'union', 'set', 'values', 'into', 'natural',
        ];
        $patterns = [
            '/\bFROM\s+' . $t . '\s+(?:AS\s+)?([a-zA-Z_][a-zA-Z0-9_]*)/i',
            '/\b(?:LEFT|RIGHT|INNER|CROSS|NATURAL)\s+(?:OUTER\s+)?JOIN\s+' . $t . '\s+(?:AS\s+)?([a-zA-Z_][a-zA-Z0-9_]*)/i',
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $sql, $m)) {
                continue;
            }
            $alias = strtolower($m[1]);
            if (in_array($alias, $keywords, true)) {
                continue;
            }
            $clean = preg_replace('/[^a-z_0-9]/', '', $alias) ?? '';
            if ($clean !== '') {
                return $clean;
            }
        }

        return '';
    }

    /**
     * Branch filter on operational tables (purchase_orders, suppliers, etc.).
     *
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeOperationalSql(string $sql, array $params, string $alias, string $table, ?int $branchId = null): array
    {
        if ($branchId !== null && $branchId > 0) {
            $col = ($alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '') . 'branch_id';
            $key = '_ops_bf_single';
            return [$sql . (preg_match('/\bWHERE\b/i', $sql) ? ' AND ' : ' WHERE ') . $col . ' = :' . $key, array_merge($params, [$key => $branchId])];
        }
        if (stripos($sql, $table) === false) {
            return [$sql, $params];
        }
        if (!$this->tableColumnExists($table, 'branch_id')) {
            return [$sql, $params];
        }
        $resolvedAlias = $this->sqlTableAlias($sql, $table);
        $safeAlias = preg_replace('/[^a-z_0-9]/', '', $alias);
        if ($resolvedAlias !== '') {
            if ($safeAlias !== '' && $safeAlias !== $resolvedAlias) {
                return [$sql, $params];
            }
            $safeAlias = $resolvedAlias;
        } elseif ($safeAlias !== '' && !preg_match('/\b' . preg_quote($safeAlias, '/') . '\b/i', $sql)) {
            return [$sql, $params];
        }
        return $this->accountingBranch()->appendFilter($sql, $params, $safeAlias, 'branch_id');
    }

    /**
     * LEFT JOIN journal entries — keep rows without JE; constrain when JE exists.
     *
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function scopeOptionalJournalEntrySql(string $sql, array $params, string $alias = 'je', ?int $branchId = null): array
    {
        $safe = preg_replace('/[^a-z_]/', '', $alias);
        if ($safe !== '' && !preg_match('/\b' . preg_quote($safe, '/') . '\b/i', $sql)) {
            return [$sql, $params];
        }
        if (!$this->tableColumnExists('rateb_journal_entries', 'branch_id')) {
            return [$sql, $params];
        }
        if ($branchId !== null && $branchId > 0) {
            $key = '_oje_bf_single';
            return [$sql . ' AND (' . $safe . '.id IS NULL OR ' . $safe . '.branch_id = :' . $key . ')', array_merge($params, [$key => $branchId])];
        }
        $ids = $this->accountingBranch()->effectiveBranchIds();
        if ($ids === []) {
            return [$sql, $params];
        }
        $parts = [];
        $extra = [];
        foreach ($ids as $i => $id) {
            $key = '_oje_bf_' . $i;
            $parts[] = ':' . $key;
            $extra[$key] = $id;
        }
        return [$sql . ' AND (' . $safe . '.id IS NULL OR ' . $safe . '.branch_id IN (' . implode(',', $parts) . '))', array_merge($params, $extra)];
    }

    /** @param array<string, mixed> $params @return array<int, array<string, mixed>> */
    protected function operationalScopedQuery(string $sql, array $params, string $alias, string $table): array
    {
        [$sql, $params] = $this->scopeOperationalSql($sql, $params, $alias, $table);

        return $this->executeScopedSql($sql, $params);
    }

    /** @param array<string, mixed> $params @return array<int, array<string, mixed>> */
    protected function executeScopedSql(string $sql, array $params): array
    {
        return \Rateb\App\Core\Database::fetchAll($sql, $params);
    }

    /** @param array<string, mixed> $params @return array<string, mixed>|null */
    protected function executeScopedSqlOne(string $sql, array $params): ?array
    {
        return \Rateb\App\Core\Database::fetchOne($sql, $params);
    }
}
