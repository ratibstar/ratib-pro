<?php
declare(strict_types=1);

namespace App\Accounting\Drift;

use App\Accounting\Core\AccountingIdempotency;
use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Projections\ProjectionRepository;
use App\Accounting\Reporting\AccountingReportService;
use App\Accounting\Support\AccountingConfig;

/**
 * Compares event store ↔ ledger ↔ snapshots ↔ consolidated reports.
 */
final class AccountingDriftDetector
{
    public function __construct(
        private readonly AccountingEventRepository $events = new AccountingEventRepository(),
        private readonly AccountingIdempotency $idempotency = new AccountingIdempotency(),
        private readonly AccountingReportService $reports = new AccountingReportService(),
        private readonly ProjectionRepository $projections = new ProjectionRepository(),
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::driftDetectionEnabled();
    }

    /**
     * @param array<string, mixed> $params company_id, branch_id?, period_from, period_to
     */
    public function detectDrift(array $params): DriftReport
    {
        $companyId = isset($params['company_id']) ? (int) $params['company_id'] : null;
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? $params['period_start'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? $params['period_end'] ?? date('Y-m-d'));

        $missing = [];
        $duplicate = [];
        $mismatched = [];
        $orphan = [];

        $filters = array_filter([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
        ]);

        // 1. Event store vs idempotency / ledger posting
        if ($this->events->tableExists()) {
            $storedEvents = $this->events->findByFilters(array_merge($filters, ['limit' => 5000]));
            $seenUuid = [];

            foreach ($storedEvents as $event) {
                if ($event->status === 'processed' && !$this->idempotency->wasProcessed($event->eventUuid)) {
                    $missing[] = [
                        'layer' => 'event_store_vs_idempotency',
                        'event_uuid' => $event->eventUuid,
                        'issue' => 'processed_in_store_not_in_idempotency',
                    ];
                }
                if (isset($seenUuid[$event->eventUuid])) {
                    $duplicate[] = [
                        'layer' => 'event_store',
                        'event_uuid' => $event->eventUuid,
                        'issue' => 'duplicate_event_uuid',
                    ];
                }
                $seenUuid[$event->eventUuid] = true;

                if ($event->status === 'failed') {
                    $orphan[] = [
                        'layer' => 'event_store',
                        'event_uuid' => $event->eventUuid,
                        'issue' => 'failed_event_never_posted',
                    ];
                }
            }
        }

        // 2. Ledger (normalized read) vs event store totals
        if ($companyId !== null && $companyId > 0) {
            $tb = $this->reports->trialBalance($filters);
            $ledgerDebit = (float) ($tb['totals']['debit'] ?? 0);
            $ledgerCredit = (float) ($tb['totals']['credit'] ?? 0);

            $eventSum = 0.0;
            if ($this->events->tableExists()) {
                foreach ($this->events->findByFilters(array_merge($filters, ['status' => 'processed', 'limit' => 5000])) as $ev) {
                    $eventSum += (float) ($ev->payload['amount'] ?? 0);
                }
            }

            $ledgerTotal = $ledgerDebit + $ledgerCredit;
            if ($eventSum > 0 && abs($eventSum - $ledgerTotal) > 0.05) {
                $mismatched[] = [
                    'layer' => 'event_store_vs_ledger',
                    'expected_event_sum' => round($eventSum, 2),
                    'ledger_tb_total' => round($ledgerTotal, 2),
                    'delta' => round($ledgerTotal - $eventSum, 2),
                ];
            }

            // 3. Snapshots vs ledger
            $snapRows = $this->projections->fetchSnapshotPayloads(
                'accounting_trial_balance_snapshots',
                $companyId,
                $periodFrom,
                $periodTo,
                $branchId
            );
            if ($snapRows !== []) {
                $snapDebit = 0.0;
                $snapCredit = 0.0;
                foreach ($snapRows as $snap) {
                    $snapDebit += (float) ($snap['debit'] ?? $snap['debit_total'] ?? 0);
                    $snapCredit += (float) ($snap['credit'] ?? $snap['credit_total'] ?? 0);
                }
                if (abs($snapDebit - $ledgerDebit) > 0.05 || abs($snapCredit - $ledgerCredit) > 0.05) {
                    $mismatched[] = [
                        'layer' => 'snapshots_vs_ledger',
                        'snapshot_debit' => round($snapDebit, 2),
                        'ledger_debit' => round($ledgerDebit, 2),
                        'snapshot_credit' => round($snapCredit, 2),
                        'ledger_credit' => round($ledgerCredit, 2),
                    ];
                }
            }

            // 4. Consolidated vs snapshots
            $consRows = $this->projections->fetchSnapshotPayloads(
                'accounting_consolidated_trial_balance',
                $companyId,
                $periodFrom,
                $periodTo,
                $branchId
            );
            if ($consRows !== [] && $snapRows !== []) {
                $consDebit = 0.0;
                foreach ($consRows as $c) {
                    $consDebit += (float) ($c['debit'] ?? $c['debit_total'] ?? 0);
                }
                if (abs($consDebit - $snapDebit) > 0.05) {
                    $mismatched[] = [
                        'layer' => 'consolidated_vs_snapshots',
                        'consolidated_debit' => round($consDebit, 2),
                        'snapshot_debit' => round($snapDebit, 2),
                    ];
                }
            }

            if (abs($ledgerDebit - $ledgerCredit) > 0.05 && ($ledgerDebit + $ledgerCredit) > 0) {
                $mismatched[] = [
                    'layer' => 'ledger_imbalance',
                    'debit' => round($ledgerDebit, 2),
                    'credit' => round($ledgerCredit, 2),
                ];
            }
        }

        $findings = [
            'missing_entries' => $missing,
            'duplicate_entries' => $duplicate,
            'mismatched_amounts' => $mismatched,
            'orphan_transactions' => $orphan,
            'summary' => [
                'missing' => count($missing),
                'duplicate' => count($duplicate),
                'mismatched' => count($mismatched),
                'orphan' => count($orphan),
                'has_drift' => ($missing !== [] || $duplicate !== [] || $mismatched !== [] || $orphan !== []),
            ],
        ];

        $reportId = $this->projections->saveDriftReport($companyId, $periodFrom, $periodTo, $findings, $branchId);

        return new DriftReport(
            missingEntries: $missing,
            duplicateEntries: $duplicate,
            mismatchedAmounts: $mismatched,
            orphanTransactions: $orphan,
            reportId: $reportId,
        );
    }
}
