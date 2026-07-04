<?php
declare(strict_types=1);

namespace App\Accounting\Normalization;

use App\Accounting\Infrastructure\AccountingConnectionFactory;
use App\Accounting\Reporting\AccountingReportRow;

/**
 * Converts all four accounting schemas into unified AccountingReportRow DTOs.
 * READ-ONLY — never writes to source databases.
 */
final class AccountingNormalizer
{
    public function __construct(
        private readonly ?\PDO $pdo = null,
    ) {
    }

    /**
     * @param array<string, mixed> $filters company_id, branch_id, from_date, to_date
     * @return list<AccountingReportRow>
     */
    public function normalizeAll(array $filters = []): array
    {
        return array_merge(
            $this->fromRatebErp($filters),
            $this->fromMainSite($filters),
            $this->fromControlPanel($filters),
            $this->fromLedger($filters),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingReportRow>
     */
    public function fromRatebErp(array $filters = []): array
    {
        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'rateb_journal_lines')) {
            return [];
        }

        $where = ['je.status = \'posted\''];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'je.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['branch_id']) && $this->columnExists($pdo, 'rateb_journal_lines', 'branch_id')) {
            $where[] = 'jl.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'je.entry_date >= :from_date';
            $params['from_date'] = (string) $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'je.entry_date <= :to_date';
            $params['to_date'] = (string) $filters['to_date'];
        }

        $sql = 'SELECT jl.debit, jl.credit, je.entry_date, je.company_id, je.source_type, je.source_id,
                       coa.code AS account_code, coa.name AS account_name, jl.branch_id
                FROM rateb_journal_lines jl
                INNER JOIN rateb_journal_entries je ON je.id = jl.journal_entry_id
                LEFT JOIN rateb_chart_of_accounts coa ON coa.id = jl.account_id
                WHERE ' . implode(' AND ', $where);

        return $this->fetchRows($pdo, $sql, $params, 'rateb-erp');
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingReportRow>
     */
    public function fromMainSite(array $filters = []): array
    {
        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'journal_entry_lines')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (!empty($filters['from_date'])) {
            $where[] = 'je.entry_date >= :from_date';
            $params['from_date'] = (string) $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'je.entry_date <= :to_date';
            $params['to_date'] = (string) $filters['to_date'];
        }

        $accountJoin = $this->tableExists($pdo, 'financial_accounts')
            ? 'LEFT JOIN financial_accounts fa ON fa.id = jel.account_id'
            : '';
        $codeExpr = $this->tableExists($pdo, 'financial_accounts')
            ? 'COALESCE(fa.account_code, CAST(jel.account_id AS CHAR))'
            : 'CAST(jel.account_id AS CHAR)';
        $nameExpr = $this->tableExists($pdo, 'financial_accounts')
            ? 'COALESCE(fa.account_name, jel.description, \'\')'
            : 'COALESCE(jel.description, \'\')';

        $sql = "SELECT jel.debit, jel.credit, je.entry_date, je.id AS source_id,
                       {$codeExpr} AS account_code, {$nameExpr} AS account_name
                FROM journal_entry_lines jel
                INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
                {$accountJoin}
                WHERE " . implode(' AND ', $where);

        return $this->fetchRows($pdo, $sql, $params, 'main-site', null, null, 'journal_entry');
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingReportRow>
     */
    public function fromControlPanel(array $filters = []): array
    {
        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'control_journal_entry_lines')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'je.country_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'je.entry_date >= :from_date';
            $params['from_date'] = (string) $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'je.entry_date <= :to_date';
            $params['to_date'] = (string) $filters['to_date'];
        }

        $sql = 'SELECT jl.debit, jl.credit, je.entry_date, je.country_id AS company_id,
                       COALESCE(jl.account_code, \'\') AS account_code,
                       COALESCE(jl.account_name, jl.description, \'\') AS account_name,
                       je.id AS source_id
                FROM control_journal_entry_lines jl
                INNER JOIN control_journal_entries je ON je.id = jl.journal_entry_id
                WHERE ' . implode(' AND ', $where);

        return $this->fetchRows($pdo, $sql, $params, 'control-panel', null, null, 'control_journal_entry');
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<AccountingReportRow>
     */
    public function fromLedger(array $filters = []): array
    {
        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null || !$this->tableExists($pdo, 'ledger_entries')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'lj.agency_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        $sql = 'SELECT le.debit, le.credit, lj.posted_at AS entry_date, lj.agency_id AS company_id,
                       COALESCE(la.code, CAST(la.id AS CHAR)) AS account_code,
                       COALESCE(la.name, le.description, \'\') AS account_name,
                       lj.reference_type, lj.reference_id
                FROM ledger_entries le
                INNER JOIN ledger_journals lj ON lj.id = le.ledger_journal_id
                INNER JOIN ledger_accounts la ON la.id = le.ledger_account_id
                WHERE ' . implode(' AND ', $where);

        return $this->fetchRows($pdo, $sql, $params, 'ledger');
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<AccountingReportRow>
     */
    private function fetchRows(
        \PDO $pdo,
        string $sql,
        array $params,
        string $sourceSystem,
        ?int $defaultCompanyId = null,
        ?int $defaultBranchId = null,
        ?string $referenceType = null
    ): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('AccountingNormalizer fetch failed (' . $sourceSystem . '): ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = new AccountingReportRow(
                accountCode: (string) ($row['account_code'] ?? ''),
                accountName: (string) ($row['account_name'] ?? ''),
                debit: (float) ($row['debit'] ?? 0),
                credit: (float) ($row['credit'] ?? 0),
                sourceSystem: $sourceSystem,
                companyId: isset($row['company_id']) ? (int) $row['company_id'] : $defaultCompanyId,
                branchId: isset($row['branch_id']) ? (int) $row['branch_id'] : $defaultBranchId,
                entryDate: isset($row['entry_date']) ? (string) $row['entry_date'] : null,
                referenceType: (string) ($row['source_type'] ?? $row['reference_type'] ?? $referenceType),
                referenceId: $row['source_id'] ?? $row['reference_id'] ?? null,
            );
        }

        return $out;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
