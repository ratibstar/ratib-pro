<?php
declare(strict_types=1);

namespace App\Accounting\Projections;

use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\EventStore\AccountingEventStore;
use App\Accounting\Normalization\AccountingNormalizer;

/**
 * Rebuilds materialized snapshots from event store + normalization — NEVER touches accounting_events.
 */
final class AccountingSnapshotRebuilder
{
    public function __construct(
        private readonly AccountingEventStore $eventStore = new AccountingEventStore(),
        private readonly AccountingProjectionEngine $projections = new AccountingProjectionEngine(),
        private readonly AccountingNormalizer $normalizer = new AccountingNormalizer(),
        private readonly ProjectionRepository $repository = new ProjectionRepository(),
        private readonly AccountingAuditService $audit = new AccountingAuditService(),
    ) {
    }

    /**
     * @return array{ok:bool, message:string, counts?:array<string,int>, events_replayed?:int}
     */
    public function rebuild(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'Invalid company_id'];
        }

        if (!$this->eventStore->isAvailable()) {
            return ['ok' => false, 'message' => 'Event store unavailable'];
        }

        if ($this->repository->isPeriodHardClosed($companyId, $periodFrom, $periodTo, $branchId)) {
            return ['ok' => false, 'message' => 'Period is hard closed — reopen before rebuild'];
        }

        $this->audit->log('snapshot_rebuild_start', 'projection', 'running', null, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        $events = $this->eventStore->fetchForReplay([
            'company_id' => $companyId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
            'status' => 'processed',
            'limit' => 5000,
        ]);

        $normalizedRows = $this->normalizer->normalizeAll([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'from_date' => $periodFrom,
            'to_date' => $periodTo,
        ]);

        $counts = $this->projections->projectPeriod([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'rebuild_from_events' => count($events),
            'rebuild_from_normalized_rows' => count($normalizedRows),
        ]);

        $this->audit->log('snapshot_rebuild_complete', 'projection', 'processed', null, [
            'company_id' => $companyId,
            'counts' => $counts,
        ]);

        return [
            'ok' => true,
            'message' => 'Snapshots rebuilt',
            'counts' => $counts,
            'events_replayed' => count($events),
        ];
    }
}
