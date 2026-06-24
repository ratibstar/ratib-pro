<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\JournalEntry;

/** Applies branch isolation to accounting SQL (journal entries, lines, bank, cost centers). */
trait AccountingBranchScope
{
    private ?BranchIsolationService $accountingBranchIsolation = null;

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
        return $this->accountingBranch()->appendFilter($sql, $params, $alias, 'branch_id');
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
        if (stripos($sql, 'rateb_journal_lines') !== false) {
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
        if (stripos($sql, 'rateb_bank_accounts') === false) {
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
        if (stripos($sql, 'rateb_cost_centers') === false) {
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
        return (new JournalEntry())->query($sql, $params);
    }

    /** @param array<string, mixed> $params @return array<string, mixed>|null */
    protected function journalScopedQueryOne(string $sql, array $params, string $entryAlias = 'e', ?int $branchId = null): ?array
    {
        [$sql, $params] = $this->scopeJournalEntrySql($sql, $params, $entryAlias, $branchId);
        $row = (new JournalEntry())->queryOne($sql, $params);
        return $row ?: null;
    }
}
