<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\JournalEntry;

/** Removes inter-branch Due To/From balances from consolidated statements. */
final class ConsolidationEliminationService
{
    use AccountingBranchScope;

    /** @return array<string, float> */
    public function eliminationAdjustments(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $dueFromId = $this->accountIdByCode($companyId, '1350');
        $dueToId = $this->accountIdByCode($companyId, '2150');
        $dueFromBal = $dueFromId > 0 ? $this->accountBalance($companyId, $dueFromId, $from, $to) : 0.0;
        $dueToBal = $dueToId > 0 ? $this->accountBalance($companyId, $dueToId, $from, $to) : 0.0;
        $offset = min(abs($dueFromBal), abs($dueToBal));
        return [
            'due_from_balance' => $dueFromBal,
            'due_to_balance' => $dueToBal,
            'asset_adjustment' => -$offset,
            'liability_adjustment' => -$offset,
            'pl_adjustment' => 0.0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function interBranchBalances(int $companyId): array
    {
        $dueFromId = $this->accountIdByCode($companyId, '1350');
        $dueToId = $this->accountIdByCode($companyId, '2150');
        $ids = array_values(array_unique(array_filter(
            [$dueFromId, $dueToId],
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $params = ['cid' => $companyId, 'due_from' => $dueFromId, 'due_to' => $dueToId];
        foreach ($ids as $i => $id) {
            $key = 'aid' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $sql = 'SELECT e.branch_id, b.name AS branch_name, b.code AS branch_code,
                       COALESCE(SUM(CASE WHEN l.account_id = :due_from THEN l.debit - l.credit ELSE 0 END), 0) AS due_from,
                       COALESCE(SUM(CASE WHEN l.account_id = :due_to THEN l.credit - l.debit ELSE 0 END), 0) AS due_to
                FROM rateb_journal_entries e
                INNER JOIN rateb_journal_lines l ON l.journal_entry_id = e.id
                LEFT JOIN rateb_branches b ON b.id = e.branch_id
                WHERE e.company_id = :cid AND e.status = \'posted\'
                  AND l.account_id IN (' . implode(', ', $placeholders) . ')
                GROUP BY e.branch_id, b.name, b.code
                ORDER BY b.name';

        return (new JournalEntry())->query($sql, $params);
    }

    private function accountIdByCode(int $companyId, string $code): int
    {
        // Use AccountingService so legacy codes (1350/2150) resolve after Saudi COA rename.
        return (int) ((new AccountingService())->accountIdByCode($companyId, $code) ?? 0);
    }

    private function accountBalance(int $companyId, int $accountId, ?string $from, ?string $to): float
    {
        $sql = "SELECT COALESCE(SUM(l.debit - l.credit), 0) AS bal
                FROM rateb_journal_lines l
                INNER JOIN rateb_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
                WHERE e.company_id = :cid AND l.account_id = :aid";
        $params = ['cid' => $companyId, 'aid' => $accountId];
        if ($from) {
            $sql .= ' AND e.entry_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND e.entry_date <= :to';
            $params['to'] = $to;
        }
        $row = $this->journalScopedQueryOne($sql, $params, 'e', null);
        return (float) ($row['bal'] ?? 0);
    }
}
