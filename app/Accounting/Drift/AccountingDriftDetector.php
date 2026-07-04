<?php
declare(strict_types=1);

namespace App\Accounting\Drift;

use App\Accounting\Core\AccountingIdempotency;
use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Projections\ProjectionRepository;
use App\Accounting\Reporting\AccountingReportService;
use App\Accounting\Support\AccountingConfig;

/**
 * Detects mismatch between EVENT STORE vs DATABASE STATE.
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
     * @param array<string, mixed> $params company_id, period_start, period_end
     */
    public function detectDrift(array $params): DriftReport
    {
        $companyId = isset($params['company_id']) ? (int) $params['company_id'] : null;
        $periodStart = (string) ($params['period_start'] ?? date('Y-m-01'));
        $periodEnd = (string) ($params['period_end'] ?? date('Y-m-d'));

        $missing = [];
        $duplicate = [];
        $mismatched = [];
        $orphan = [];

        if (!$this->events->tableExists()) {
            return new DriftReport(
                missingEntries: [['reason' => 'accounting_events table unavailable']],
            );
        }

        $filters = array_filter([
            'company_id' => $companyId,
            'from_date' => $periodStart,
            'to_date' => $periodEnd,
            'limit' => 5000,
        ]);

        $storedEvents = $this->events->findByFilters($filters);
        $seenUuid = [];

        foreach ($storedEvents as $event) {
            if ($event->status === 'processed' && !$this->idempotency->wasProcessed($event->eventUuid)) {
                $missing[] = [
                    'event_uuid' => $event->eventUuid,
                    'issue' => 'processed_in_store_not_in_idempotency',
                    'amount' => $event->payload['amount'] ?? null,
                ];
            }

            if (isset($seenUuid[$event->eventUuid])) {
                $duplicate[] = [
                    'event_uuid' => $event->eventUuid,
                    'issue' => 'duplicate_event_uuid_in_store',
                ];
            }
            $seenUuid[$event->eventUuid] = true;

            if ($event->status === 'failed') {
                $orphan[] = [
                    'event_uuid' => $event->eventUuid,
                    'source_system' => $event->sourceSystem,
                    'issue' => 'failed_event_never_posted',
                ];
            }
        }

        if ($companyId !== null && $companyId > 0) {
            $expectedTotal = 0.0;
            foreach ($storedEvents as $event) {
                if ($event->status === 'processed') {
                    $expectedTotal += (float) ($event->payload['amount'] ?? 0);
                }
            }

            $tb = $this->reports->trialBalance([
                'company_id' => $companyId,
                'from_date' => $periodStart,
                'to_date' => $periodEnd,
            ]);
            $actualTotal = ($tb['totals']['debit'] ?? 0) + ($tb['totals']['credit'] ?? 0);

            if (abs($expectedTotal - $actualTotal) > 0.05 && $expectedTotal > 0) {
                $mismatched[] = [
                    'issue' => 'event_amount_sum_vs_trial_balance_totals',
                    'expected_event_sum' => round($expectedTotal, 2),
                    'actual_tb_total' => round($actualTotal, 2),
                    'delta' => round($actualTotal - $expectedTotal, 2),
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
            ],
        ];

        $reportId = $this->projections->saveDriftReport($companyId, $periodStart, $periodEnd, $findings);

        return new DriftReport(
            missingEntries: $missing,
            duplicateEntries: $duplicate,
            mismatchedAmounts: $mismatched,
            orphanTransactions: $orphan,
            reportId: $reportId,
        );
    }
}
