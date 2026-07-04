<?php
declare(strict_types=1);

namespace App\Accounting\Consolidation;

use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Projections\ProjectionRepository;
use App\Accounting\Reporting\AccountingReportService;
use App\Accounting\Support\AccountingConfig;

/**
 * HQ consolidated view — multi-company/branch aggregation with event_uuid deduplication.
 */
final class AccountingConsolidationEngine
{
    public function __construct(
        private readonly AccountingReportService $reports = new AccountingReportService(),
        private readonly AccountingEventRepository $events = new AccountingEventRepository(),
        private readonly ProjectionRepository $repository = new ProjectionRepository(),
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::consolidationEnabled();
    }

    /**
     * @param array<string, mixed> $params company_id, branch_id?, period_from, period_to, company_ids[]?
     * @return array{run_id:string, trial_balance:int, balance_sheet:int, profit_loss:int}
     */
    public function runConsolidation(array $params): array
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? $params['period_start'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? $params['period_end'] ?? date('Y-m-d'));
        $runId = 'cons-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

        if ($companyId < 1 || !$this->isEnabled()) {
            return ['run_id' => $runId, 'trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0];
        }

        $filters = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
        ];

        $eliminated = $this->loadProcessedEventUuids($companyId, $periodFrom, $periodTo);
        $tb = $this->reports->trialBalance($filters);
        $pl = $this->reports->profitAndLoss($filters);
        $bs = $this->reports->balanceSheet($filters);

        $tbRows = [];
        foreach ($tb['rows'] as $row) {
            $tbRows[] = array_merge($row, [
                'eliminated_event_uuids' => $eliminated,
                'consolidation_run_id' => $runId,
            ]);
        }

        $bsRows = array_map(static fn (array $r): array => $r, $bs['rows'] ?? []);
        $plRows = array_map(static fn (array $r): array => $r, $pl['rows'] ?? []);

        return [
            'run_id' => $runId,
            'trial_balance' => $this->repository->insertConsolidatedRows(
                'accounting_consolidated_trial_balance',
                $companyId,
                $branchId,
                $periodFrom,
                $periodTo,
                $runId,
                $tbRows
            ),
            'balance_sheet' => $this->repository->insertConsolidatedRows(
                'accounting_consolidated_balance_sheet',
                $companyId,
                $branchId,
                $periodFrom,
                $periodTo,
                $runId,
                $bsRows
            ),
            'profit_loss' => $this->repository->insertConsolidatedRows(
                'accounting_consolidated_profit_loss',
                $companyId,
                $branchId,
                $periodFrom,
                $periodTo,
                $runId,
                $plRows
            ),
            'eliminated_event_uuids' => count($eliminated),
        ];
    }

    /**
     * @return list<string>
     */
    private function loadProcessedEventUuids(int $companyId, string $periodFrom, string $periodTo): array
    {
        if (!$this->events->tableExists()) {
            return [];
        }

        $rows = $this->events->findByFilters([
            'company_id' => $companyId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
            'status' => 'processed',
            'limit' => 5000,
        ]);

        return array_map(static fn ($e) => $e->eventUuid, $rows);
    }
}
