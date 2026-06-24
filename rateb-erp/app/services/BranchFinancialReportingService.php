<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Branch;

/** Branch-level and consolidated financial statements (P&L, BS, Cash Flow). */
final class BranchFinancialReportingService
{
    use AccountingBranchScope;

    public function __construct(
        private ?ConsolidationEliminationService $elimination = null,
        private ?BranchIsolationService $isolation = null,
    ) {
        $this->elimination ??= new ConsolidationEliminationService();
        $this->isolation ??= new BranchIsolationService();
    }

    /** @return array<string, mixed> */
    public function profitAndLossByBranch(int $companyId, int $branchId, ?string $from = null, ?string $to = null): array
    {
        $this->isolation->assertCanAccess($branchId);
        return $this->buildPl($companyId, $branchId, $from, $to);
    }

    /** @return array<string, mixed> */
    public function balanceSheetByBranch(int $companyId, int $branchId, ?string $asOf = null): array
    {
        $this->isolation->assertCanAccess($branchId);
        return $this->buildBs($companyId, $branchId, $asOf);
    }

    /** @return array<string, mixed> */
    public function cashFlowByBranch(int $companyId, int $branchId, ?string $from = null, ?string $to = null): array
    {
        $this->isolation->assertCanAccess($branchId);
        $from = $from ?? date('Y-01-01');
        $to = $to ?? date('Y-m-d');
        $operating = $this->cashMovement($companyId, $branchId, $from, $to, ['1100', '115', '1200', '2100']);
        $investing = $this->cashMovement($companyId, $branchId, $from, $to, ['150', '151', '152', '153']);
        $financing = $this->cashMovement($companyId, $branchId, $from, $to, ['320', '250']);
        return [
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'net_cash_flow' => $operating + $investing + $financing,
        ];
    }

    /** @return array<string, mixed> */
    public function consolidatedProfitAndLoss(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $combined = ['revenue' => 0.0, 'expenses' => 0.0, 'net' => 0.0, 'branches' => []];
        foreach ($this->branchIdsForConsolidation($companyId) as $bid) {
            $pl = $this->buildPl($companyId, $bid, $from, $to);
            $combined['revenue'] += (float) ($pl['revenue'] ?? 0);
            $combined['expenses'] += (float) ($pl['expenses'] ?? 0);
            $combined['branches'][] = $pl;
        }
        $elim = $this->elimination->eliminationAdjustments($companyId, $from, $to);
        $combined['elimination'] = $elim;
        $combined['net'] = $combined['revenue'] - $combined['expenses'] + (float) ($elim['pl_adjustment'] ?? 0);
        return $combined;
    }

    /** @return array<string, mixed> */
    public function consolidatedBalanceSheet(int $companyId, ?string $asOf = null): array
    {
        $combined = ['assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0, 'branches' => []];
        foreach ($this->branchIdsForConsolidation($companyId) as $bid) {
            $bs = $this->buildBs($companyId, $bid, $asOf);
            $combined['assets'] += (float) ($bs['assets'] ?? 0);
            $combined['liabilities'] += (float) ($bs['liabilities'] ?? 0);
            $combined['equity'] += (float) ($bs['equity'] ?? 0);
            $combined['branches'][] = $bs;
        }
        $elim = $this->elimination->eliminationAdjustments($companyId, null, $asOf);
        $combined['elimination'] = $elim;
        $combined['assets'] += (float) ($elim['asset_adjustment'] ?? 0);
        $combined['liabilities'] += (float) ($elim['liability_adjustment'] ?? 0);
        return $combined;
    }

    /** @return array<string, mixed> */
    public function consolidatedCashFlow(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $combined = ['operating' => 0.0, 'investing' => 0.0, 'financing' => 0.0, 'net_cash_flow' => 0.0, 'branches' => []];
        foreach ($this->branchIdsForConsolidation($companyId) as $bid) {
            $cf = $this->cashFlowByBranch($companyId, $bid, $from, $to);
            $combined['operating'] += (float) ($cf['operating'] ?? 0);
            $combined['investing'] += (float) ($cf['investing'] ?? 0);
            $combined['financing'] += (float) ($cf['financing'] ?? 0);
            $combined['net_cash_flow'] += (float) ($cf['net_cash_flow'] ?? 0);
            $combined['branches'][] = $cf;
        }
        return $combined;
    }

    /** @return array<int, int> */
    private function branchIdsForConsolidation(int $companyId): array
    {
        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_bootstrap_branch_context')) {
            rateb_bootstrap_branch_context($companyId);
        }
        $ids = $this->isolation->effectiveBranchIds();
        if ($ids !== []) {
            return $ids;
        }
        return array_map('intval', array_column((new Branch())->query(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st',
            ['cid' => $companyId, 'st' => 'active']
        ), 'id'));
    }

    /** @return array<string, mixed> */
    private function buildPl(int $companyId, int $branchId, ?string $from, ?string $to): array
    {
        $sql = "SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_chart_of_accounts a
                INNER JOIN rateb_journal_lines l ON l.account_id = a.id
                INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                WHERE a.company_id = :cid AND a.account_type IN ('revenue','expense') AND a.is_active = 1";
        $params = ['cid' => $companyId];
        if ($from) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $to;
        }
        $sql .= ' GROUP BY a.id ORDER BY a.code';
        $lines = $this->journalScopedQuery($sql, $params, 'e', $branchId);
        $revenue = 0.0;
        $expenses = 0.0;
        foreach ($lines as $line) {
            $dr = (float) ($line['total_debit'] ?? 0);
            $cr = (float) ($line['total_credit'] ?? 0);
            if (($line['account_type'] ?? '') === 'revenue') {
                $revenue += $cr - $dr;
            } else {
                $expenses += $dr - $cr;
            }
        }
        return ['branch_id' => $branchId, 'revenue' => $revenue, 'expenses' => $expenses, 'net' => $revenue - $expenses, 'lines' => $lines, 'from' => $from, 'to' => $to];
    }

    /** @return array<string, mixed> */
    private function buildBs(int $companyId, int $branchId, ?string $asOf): array
    {
        $sql = "SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                       COALESCE(SUM(l.debit), 0) AS total_debit,
                       COALESCE(SUM(l.credit), 0) AS total_credit
                FROM rateb_chart_of_accounts a
                LEFT JOIN rateb_journal_lines l ON l.account_id = a.id
                LEFT JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                WHERE a.company_id = :cid AND a.is_active = 1";
        $params = ['cid' => $companyId];
        if ($asOf) {
            $sql .= ' AND (e.id IS NULL OR e.entry_date <= :asof)';
            $params['asof'] = $asOf;
        }
        $sql .= ' GROUP BY a.id ORDER BY a.code';
        $lines = $this->journalScopedQuery($sql, $params, 'e', $branchId);
        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;
        foreach ($lines as $line) {
            $dr = (float) ($line['total_debit'] ?? 0);
            $cr = (float) ($line['total_credit'] ?? 0);
            $type = (string) ($line['account_type'] ?? '');
            if ($type === 'asset') {
                $assets += $dr - $cr;
            } elseif ($type === 'liability') {
                $liabilities += $cr - $dr;
            } elseif ($type === 'equity') {
                $equity += $cr - $dr;
            }
        }
        return ['branch_id' => $branchId, 'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'lines' => $lines, 'as_of' => $asOf];
    }

    /** @param array<int, string> $codePrefixes */
    private function cashMovement(int $companyId, int $branchId, string $from, string $to, array $codePrefixes): float
    {
        $parts = [];
        $params = ['cid' => $companyId, 'from' => $from, 'to' => $to];
        foreach ($codePrefixes as $i => $pfx) {
            $key = 'pfx' . $i;
            $parts[] = 'a.code LIKE :' . $key;
            $params[$key] = $pfx . '%';
        }
        $sql = "SELECT COALESCE(SUM(l.debit - l.credit), 0) AS net
                FROM rateb_journal_lines l
                INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                INNER JOIN rateb_chart_of_accounts a ON a.id = l.account_id
                WHERE e.company_id = :cid AND e.entry_date >= :from AND e.entry_date <= :to
                  AND (" . implode(' OR ', $parts) . ')';
        $row = $this->journalScopedQueryOne($sql, $params, 'e', $branchId);
        return (float) ($row['net'] ?? 0);
    }
}
