<?php
declare(strict_types=1);

namespace App\Accounting\Projections;

use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Reporting\AccountingReportService;
use App\Accounting\Support\AccountingConfig;

/**
 * Builds materialized financial snapshots from event store + normalized multi-system reads.
 */
final class AccountingProjectionEngine
{
    public function __construct(
        private readonly ProjectionRepository $repository = new ProjectionRepository(),
        private readonly AccountingReportService $reports = new AccountingReportService(),
        private readonly AccountingEventRepository $eventRepository = new AccountingEventRepository(),
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::projectionsEnabled() && $this->repository->tableExists('accounting_trial_balance_snapshots');
    }

    /**
     * @param array<string, mixed> $context company_id, branch_id, period_start, period_end
     * @return array<string, int>
     */
    public function projectPeriod(array $context): array
    {
        if (!$this->isEnabled()) {
            return ['trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0, 'cashflow' => 0];
        }

        $companyId = (int) ($context['company_id'] ?? 0);
        $branchId = isset($context['branch_id']) ? (int) $context['branch_id'] : null;
        $periodStart = (string) ($context['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($context['period_end'] ?? date('Y-m-d'));

        if ($companyId < 1) {
            return ['trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0, 'cashflow' => 0];
        }

        if ($this->repository->isPeriodClosed($companyId, $periodStart, $periodEnd)) {
            return ['trial_balance' => 0, 'balance_sheet' => 0, 'profit_loss' => 0, 'cashflow' => 0, 'locked' => 1];
        }

        $filters = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodStart,
            'to_date' => $periodEnd,
        ];

        $eventUuids = $this->collectEventUuids($companyId, $periodStart, $periodEnd);

        $tb = $this->reports->trialBalance($filters);
        $pl = $this->reports->profitAndLoss($filters);
        $bs = $this->reports->balanceSheet($filters);
        $cf = $this->reports->cashFlow($filters);

        $tbRows = $this->enrichRows($tb['rows'], $eventUuids);
        $bsRows = $this->mapBalanceSheetRows($bs['rows'] ?? $bs, $eventUuids);
        $plRows = $this->mapProfitLossRows($pl['rows'] ?? [], $eventUuids);
        $cfRows = $this->mapCashflowRows($cf['rows'] ?? [], $eventUuids);

        return [
            'trial_balance' => $this->repository->replaceTrialBalanceSnapshots($companyId, $branchId, $periodStart, $periodEnd, $tbRows),
            'balance_sheet' => $this->repository->replaceBalanceSheetSnapshots($companyId, $branchId, $periodStart, $periodEnd, $bsRows),
            'profit_loss' => $this->repository->replaceProfitLossSnapshots($companyId, $branchId, $periodStart, $periodEnd, $plRows),
            'cashflow' => $this->repository->replaceCashflowSnapshots($companyId, $branchId, $periodStart, $periodEnd, $cfRows),
        ];
    }

    /**
     * Incremental update after a single processed event (async-safe).
     *
     * @param array<string, mixed> $event
     */
    public function afterEventProcessed(array $event, string $eventUuid): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $companyId = (int) ($event['company_id'] ?? 0);
        if ($companyId < 1) {
            return;
        }

        $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));
        $periodStart = date('Y-m-01', strtotime($entryDate) ?: time());
        $periodEnd = date('Y-m-t', strtotime($entryDate) ?: time());
        $branchId = array_key_exists('branch_id', $event) && $event['branch_id'] !== null
            ? (int) $event['branch_id']
            : null;

        try {
            $this->projectPeriod([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'trigger_event_uuid' => $eventUuid,
            ]);
        } catch (\Throwable $e) {
            error_log('AccountingProjectionEngine::afterEventProcessed: ' . $e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private function collectEventUuids(int $companyId, string $periodStart, string $periodEnd): array
    {
        if (!$this->eventRepository->tableExists()) {
            return [];
        }

        $events = $this->eventRepository->findByFilters([
            'company_id' => $companyId,
            'from_date' => $periodStart,
            'to_date' => $periodEnd,
            'status' => 'processed',
            'limit' => 5000,
        ]);

        return array_values(array_unique(array_map(static fn ($e) => $e->eventUuid, $events)));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $eventUuids
     * @return list<array<string, mixed>>
     */
    private function enrichRows(array $rows, array $eventUuids): array
    {
        foreach ($rows as &$row) {
            $row['balance'] = ($row['debit'] ?? 0) - ($row['credit'] ?? 0);
            $row['source_systems'] = [
                'systems' => [$row['source_system'] ?? 'unknown'],
                'event_uuids' => $eventUuids,
            ];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $eventUuids
     * @return list<array<string, mixed>>
     */
    private function mapBalanceSheetRows(array $rows, array $eventUuids): array
    {
        $out = [];
        foreach ($rows as $row) {
            $code = (string) ($row['account_code'] ?? '');
            $out[] = [
                'section' => $this->sectionFromCode($code),
                'account_code' => $code,
                'account_name' => $row['account_name'] ?? '',
                'amount' => ($row['debit'] ?? 0) - ($row['credit'] ?? 0),
                'source_systems' => ['event_uuids' => $eventUuids],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $eventUuids
     * @return list<array<string, mixed>>
     */
    private function mapProfitLossRows(array $rows, array $eventUuids): array
    {
        $out = [];
        foreach ($rows as $row) {
            $code = (string) ($row['account_code'] ?? '');
            $out[] = [
                'category' => strncmp($code, '4', 1) === 0 ? 'revenue' : 'expense',
                'account_code' => $code,
                'account_name' => $row['account_name'] ?? '',
                'amount' => abs(($row['credit'] ?? 0) - ($row['debit'] ?? 0)),
                'source_systems' => ['event_uuids' => $eventUuids],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $eventUuids
     * @return list<array<string, mixed>>
     */
    private function mapCashflowRows(array $rows, array $eventUuids): array
    {
        $out = [];
        foreach ($rows as $row) {
            $ref = (string) ($row['reference_type'] ?? 'operating');
            $cat = 'operating';
            if (strpos($ref, 'invest') !== false) {
                $cat = 'investing';
            } elseif (strpos($ref, 'finance') !== false || strpos($ref, 'loan') !== false) {
                $cat = 'financing';
            }
            $out[] = [
                'category' => $cat,
                'account_code' => $row['account_code'] ?? null,
                'description' => $row['account_name'] ?? null,
                'amount' => ($row['debit'] ?? 0) - ($row['credit'] ?? 0),
                'source_systems' => ['event_uuids' => $eventUuids],
            ];
        }

        return $out;
    }

    private function sectionFromCode(string $code): string
    {
        if (strncmp($code, '1', 1) === 0) {
            return 'asset';
        }
        if (strncmp($code, '2', 1) === 0) {
            return 'liability';
        }
        if (strncmp($code, '3', 1) === 0) {
            return 'equity';
        }

        return 'asset';
    }
}
