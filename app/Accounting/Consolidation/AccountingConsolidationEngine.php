<?php
declare(strict_types=1);

namespace App\Accounting\Consolidation;

use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Infrastructure\AccountingConnectionFactory;
use App\Accounting\Reporting\AccountingReportService;
use App\Accounting\Support\AccountingConfig;

/**
 * HQ consolidated financial view — SUM(all systems) with event_uuid deduplication.
 */
final class AccountingConsolidationEngine
{
    public function __construct(
        private readonly AccountingReportService $reports = new AccountingReportService(),
        private readonly AccountingEventRepository $events = new AccountingEventRepository(),
        private readonly ?\PDO $pdo = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::consolidationEnabled();
    }

    /**
     * @param array<string, mixed> $params company_id, period_start, period_end
     * @return array{run_id:string, trial_balance:int, balance_sheet:int, profit_loss:int}
     */
    public function runConsolidation(array $params): array
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $periodStart = (string) ($params['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($params['period_end'] ?? date('Y-m-d'));
        $runId = 'cons-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

        if ($companyId < 1 || !$this->isEnabled()) {
            return ['run_id' => $runId, 'trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0];
        }

        $pdo = $this->pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null) {
            return ['run_id' => $runId, 'trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0];
        }

        $seenUuids = $this->loadProcessedEventUuids($companyId, $periodStart, $periodEnd);
        $eliminated = [];

        $filters = ['company_id' => $companyId, 'from_date' => $periodStart, 'to_date' => $periodEnd];
        $tb = $this->reports->trialBalance($filters);
        $pl = $this->reports->profitAndLoss($filters);
        $bs = $this->reports->balanceSheet($filters);

        /** @var array<string, array{code:string,name:string,debit:float,credit:float,sources:list<string>}> $merged */
        $merged = [];
        foreach ($tb['rows'] as $row) {
            $code = (string) ($row['account_code'] ?? '');
            $source = (string) ($row['source_system'] ?? '');
            $key = $code;
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'code' => $code,
                    'name' => (string) ($row['account_name'] ?? ''),
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'sources' => [],
                ];
            }
            $merged[$key]['debit'] += (float) ($row['debit'] ?? 0);
            $merged[$key]['credit'] += (float) ($row['credit'] ?? 0);
            if ($source !== '' && !in_array($source, $merged[$key]['sources'], true)) {
                $merged[$key]['sources'][] = $source;
            }
        }

        foreach ($seenUuids as $uuid) {
            $eliminated[] = $uuid;
        }

        $tbCount = $this->insertConsolidatedTrialBalance($pdo, $companyId, $periodStart, $periodEnd, $runId, $merged, $eliminated);
        $bsCount = $this->insertConsolidatedBalanceSheet($pdo, $companyId, $periodStart, $periodEnd, $runId, $bs);
        $plCount = $this->insertConsolidatedProfitLoss($pdo, $companyId, $periodStart, $periodEnd, $runId, $pl);

        return [
            'run_id' => $runId,
            'trial_balance' => $tbCount,
            'balance_sheet' => $bsCount,
            'profit_loss' => $plCount,
            'eliminated_event_uuids' => count($eliminated),
        ];
    }

    /**
     * @return list<string>
     */
    private function loadProcessedEventUuids(int $companyId, string $periodStart, string $periodEnd): array
    {
        if (!$this->events->tableExists()) {
            return [];
        }

        $rows = $this->events->findByFilters([
            'company_id' => $companyId,
            'from_date' => $periodStart,
            'to_date' => $periodEnd,
            'status' => 'processed',
            'limit' => 5000,
        ]);

        return array_map(static fn ($e) => $e->eventUuid, $rows);
    }

    /**
     * @param array<string, array{code:string,name:string,debit:float,credit:float,sources:list<string>}> $merged
     * @param list<string> $eliminated
     */
    private function insertConsolidatedTrialBalance(
        \PDO $pdo,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        string $runId,
        array $merged,
        array $eliminated
    ): int {
        if (!$this->tableExists($pdo, 'accounting_consolidated_trial_balance')) {
            return 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_consolidated_trial_balance
            (company_id, period_start, period_end, account_code, account_name, debit_total, credit_total, balance, source_systems, eliminated_event_uuids, consolidation_run_id)
            VALUES (:cid, :ps, :pe, :code, :name, :dr, :cr, :bal, :src, :elim, :run)'
        );

        $n = 0;
        foreach ($merged as $row) {
            $bal = $row['debit'] - $row['credit'];
            $stmt->execute([
                'cid' => $companyId,
                'ps' => $periodStart,
                'pe' => $periodEnd,
                'code' => $row['code'],
                'name' => $row['name'],
                'dr' => $row['debit'],
                'cr' => $row['credit'],
                'bal' => $bal,
                'src' => json_encode($row['sources']),
                'elim' => json_encode($eliminated),
                'run' => $runId,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * @param array<string, mixed> $bs
     */
    private function insertConsolidatedBalanceSheet(
        \PDO $pdo,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        string $runId,
        array $bs
    ): int {
        if (!$this->tableExists($pdo, 'accounting_consolidated_balance_sheet')) {
            return 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_consolidated_balance_sheet
            (company_id, period_start, period_end, section, account_code, account_name, amount, consolidation_run_id)
            VALUES (:cid, :ps, :pe, :sec, :code, :name, :amt, :run)'
        );

        $n = 0;
        foreach ($bs['rows'] ?? [] as $row) {
            $code = (string) ($row['account_code'] ?? '');
            $section = strncmp($code, '2', 1) === 0 ? 'liability' : (strncmp($code, '3', 1) === 0 ? 'equity' : 'asset');
            $stmt->execute([
                'cid' => $companyId,
                'ps' => $periodStart,
                'pe' => $periodEnd,
                'sec' => $section,
                'code' => $code,
                'name' => $row['account_name'] ?? '',
                'amt' => ($row['debit'] ?? 0) - ($row['credit'] ?? 0),
                'run' => $runId,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * @param array<string, mixed> $pl
     */
    private function insertConsolidatedProfitLoss(
        \PDO $pdo,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        string $runId,
        array $pl
    ): int {
        if (!$this->tableExists($pdo, 'accounting_consolidated_profit_loss')) {
            return 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO accounting_consolidated_profit_loss
            (company_id, period_start, period_end, category, account_code, account_name, amount, consolidation_run_id)
            VALUES (:cid, :ps, :pe, :cat, :code, :name, :amt, :run)'
        );

        $n = 0;
        foreach ($pl['rows'] ?? [] as $row) {
            $code = (string) ($row['account_code'] ?? '');
            $stmt->execute([
                'cid' => $companyId,
                'ps' => $periodStart,
                'pe' => $periodEnd,
                'cat' => strncmp($code, '4', 1) === 0 ? 'revenue' : 'expense',
                'code' => $code,
                'name' => $row['account_name'] ?? '',
                'amt' => abs(($row['credit'] ?? 0) - ($row['debit'] ?? 0)),
                'run' => $runId,
            ]);
            $n++;
        }

        return $n;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
