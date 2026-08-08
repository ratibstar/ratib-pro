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
        // Prefixes cover legacy 1xxx codes and Saudi COA (101xx / 201xx / 30x / 102xx).
        $operating = $this->cashMovement($companyId, $branchId, $from, $to, [
            '1100', '111', '115', '1200', '2100',
            '10101', '10102', '10103', '10104', '20101',
        ]);
        $investing = $this->cashMovement($companyId, $branchId, $from, $to, [
            '150', '151', '152', '153', '159',
            '10201',
        ]);
        $financing = $this->cashMovement($companyId, $branchId, $from, $to, [
            '320', '250', '310',
            '301', '306', '20106', '202',
        ]);
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

    /** @return array<string, mixed> */
    public function consolidatedTrialBalance(int $companyId): array
    {
        $accounts = [];
        $branches = [];
        foreach ($this->hqConsolidationBranchIds($companyId) as $bid) {
            $lines = $this->trialBalanceForBranch($companyId, $bid);
            $branches[] = ['branch_id' => $bid, 'lines' => $lines];
            foreach ($lines as $line) {
                $aid = (int) ($line['id'] ?? 0);
                if ($aid < 1) {
                    continue;
                }
                if (!isset($accounts[$aid])) {
                    $accounts[$aid] = $line;
                    $accounts[$aid]['total_debit'] = 0.0;
                    $accounts[$aid]['total_credit'] = 0.0;
                }
                $accounts[$aid]['total_debit'] += (float) ($line['total_debit'] ?? 0);
                $accounts[$aid]['total_credit'] += (float) ($line['total_credit'] ?? 0);
            }
        }
        usort($accounts, static fn (array $a, array $b): int => strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')));
        return ['lines' => array_values($accounts), 'branches' => $branches];
    }

    /** @return array<string, mixed> */
    public function consolidatedGeneralLedger(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-01-01');
        $to = $to ?? date('Y-m-d');
        $entries = [];
        foreach ($this->hqConsolidationBranchIds($companyId) as $bid) {
            $sql = 'SELECT e.id, e.entry_no, e.entry_date, e.description, e.source_type, e.branch_id,
                           l.account_id, a.code AS account_code, a.name AS account_name,
                           l.debit, l.credit, l.memo
                    FROM rateb_journal_lines l
                    INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
                    INNER JOIN rateb_chart_of_accounts a ON a.id = l.account_id
                    WHERE e.company_id = :cid AND e.entry_date >= :from AND e.entry_date <= :to';
            $params = ['posted' => 'posted', 'cid' => $companyId, 'from' => $from, 'to' => $to];
            foreach ($this->journalScopedQuery($sql, $params, 'e', $bid) as $row) {
                $entries[] = $row;
            }
        }
        usort($entries, static function (array $a, array $b): int {
            $d = strcmp((string) ($a['entry_date'] ?? ''), (string) ($b['entry_date'] ?? ''));
            return $d !== 0 ? $d : ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        });
        return ['from' => $from, 'to' => $to, 'entries' => $entries];
    }

    /** @return array<string, mixed> */
    public function branchArAging(int $companyId): array
    {
        return $this->branchAgingReport($companyId, 'ar');
    }

    /** @return array<string, mixed> */
    public function branchApAging(int $companyId): array
    {
        return $this->branchAgingReport($companyId, 'ap');
    }

    /** @return array<string, mixed> */
    public function branchReceivables(int $companyId): array
    {
        return $this->branchArApSummary($companyId, 'ar');
    }

    /** @return array<string, mixed> */
    public function branchPayables(int $companyId): array
    {
        return $this->branchArApSummary($companyId, 'ap');
    }

    /** @return array<int, int> */
    private function hqConsolidationBranchIds(int $companyId): array
    {
        return array_map('intval', array_column((new Branch())->query(
            'SELECT id FROM rateb_branches WHERE company_id = :cid AND status = :st ORDER BY is_main DESC, id ASC',
            ['cid' => $companyId, 'st' => 'active']
        ), 'id'));
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
        if (!\Rateb\App\Core\BranchContext::accessAll() && \Rateb\App\Core\BranchContext::allowedIds() === []) {
            return [];
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

    /** @return array<int, array<string, mixed>> */
    private function trialBalanceForBranch(int $companyId, int $branchId): array
    {
        $sql = 'SELECT a.id, a.code, a.name, a.name_ar, a.account_type,
                COALESCE(SUM(l.debit), 0) AS total_debit,
                COALESCE(SUM(l.credit), 0) AS total_credit
            FROM rateb_chart_of_accounts a
            LEFT JOIN rateb_journal_lines l ON l.account_id = a.id
            LEFT JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = :posted
            WHERE a.company_id = :cid AND a.is_active = 1
            GROUP BY a.id ORDER BY a.code';
        return $this->journalScopedQuery($sql, ['cid' => $companyId, 'posted' => 'posted'], 'e', $branchId);
    }

    /** @return array<string, mixed> */
    private function branchArApSummary(int $companyId, string $type): array
    {
        $svc = new AccountingService();
        $rows = [];
        $grandOpen = 0.0;
        foreach ($this->hqConsolidationBranchIds($companyId) as $bid) {
            $branchRow = (new Branch())->queryOne(
                'SELECT id, name, code FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $bid, 'cid' => $companyId]
            );
            $data = $type === 'ar'
                ? $this->scopedArAp($svc, $companyId, $bid, true)
                : $this->scopedArAp($svc, $companyId, $bid, false);
            $open = (float) ($data['total_open'] ?? 0);
            $grandOpen += $open;
            $rows[] = [
                'branch_id' => $bid,
                'branch_name' => (string) ($branchRow['name'] ?? ''),
                'branch_code' => (string) ($branchRow['code'] ?? ''),
                'open_total' => $open,
                'row_count' => count($data['rows'] ?? []),
            ];
        }
        return ['rows' => $rows, 'grand_open' => $grandOpen];
    }

    /** @return array<string, mixed> */
    private function branchAgingReport(int $companyId, string $type): array
    {
        $today = date('Y-m-d');
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
        $branches = [];
        $svc = new AccountingService();
        foreach ($this->hqConsolidationBranchIds($companyId) as $bid) {
            $branchRow = (new Branch())->queryOne(
                'SELECT id, name, code FROM rateb_branches WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $bid, 'cid' => $companyId]
            );
            $data = $type === 'ar'
                ? $this->scopedArAp($svc, $companyId, $bid, true)
                : $this->scopedArAp($svc, $companyId, $bid, false);
            $branchBuckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
            foreach ($data['rows'] ?? [] as $row) {
                $dueDate = (string) ($row['due_date'] ?? $row['expected_date'] ?? $row['issued_at'] ?? $row['order_date'] ?? $today);
                $amt = (float) ($row['total_amount'] ?? 0);
                if ($type === 'ar' && ($row['status'] ?? '') === 'paid') {
                    continue;
                }
                if ($type === 'ap' && !empty($row['journal_id']) && $amt <= (float) ($row['paid_amount'] ?? 0) + 0.009) {
                    continue;
                }
                $days = (int) floor((strtotime($today) - strtotime($dueDate)) / 86400);
                $key = 'current';
                if ($days > 90) {
                    $key = 'over_90';
                } elseif ($days > 60) {
                    $key = '61_90';
                } elseif ($days > 30) {
                    $key = '31_60';
                } elseif ($days > 0) {
                    $key = '1_30';
                }
                $branchBuckets[$key] += $amt;
                $buckets[$key] += $amt;
            }
            $branches[] = [
                'branch_id' => $bid,
                'branch_name' => (string) ($branchRow['name'] ?? ''),
                'branch_code' => (string) ($branchRow['code'] ?? ''),
                'buckets' => $branchBuckets,
                'total' => array_sum($branchBuckets),
            ];
        }
        return ['buckets' => $buckets, 'branches' => $branches, 'as_of' => $today];
    }

    /** @return array<string, mixed> */
    private function scopedArAp(AccountingService $svc, int $companyId, int $branchId, bool $receivable): array
    {
        $prevFilter = \Rateb\App\Core\BranchContext::activeFilterBranchId();
        \Rateb\App\Core\BranchContext::setActiveFilterBranchId($branchId);
        try {
            return $receivable ? $svc->accountsReceivable($companyId) : $svc->accountsPayable($companyId);
        } finally {
            \Rateb\App\Core\BranchContext::setActiveFilterBranchId($prevFilter);
        }
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
