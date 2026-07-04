<?php
declare(strict_types=1);

namespace App\Accounting\Closing;

use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Projections\ProjectionRepository;

/**
 * Locks financial periods and generates immutable snapshot set.
 */
final class AccountingPeriodCloser
{
    public function __construct(
        private readonly ProjectionRepository $repository = new ProjectionRepository(),
        private readonly AccountingProjectionEngine $projections = new AccountingProjectionEngine(),
        private readonly AccountingConsolidationEngine $consolidation = new AccountingConsolidationEngine(),
        private readonly AccountingAuditService $audit = new AccountingAuditService(),
    ) {
    }

    /**
     * @return array{ok:bool, message:string, meta?:array<string,mixed>}
     */
    public function closePeriod(int $companyId, string $periodStart, string $periodEnd): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'Invalid company_id'];
        }

        if ($this->repository->isPeriodClosed($companyId, $periodStart, $periodEnd)) {
            return ['ok' => false, 'message' => 'Period already closed'];
        }

        $snapshotCounts = $this->projections->projectPeriod([
            'company_id' => $companyId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $consolidation = $this->consolidation->runConsolidation([
            'company_id' => $companyId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $meta = [
            'snapshots' => $snapshotCounts,
            'consolidation' => $consolidation,
            'closed_at' => date('c'),
        ];

        $saved = $this->repository->recordPeriodClosure($companyId, $periodStart, $periodEnd, $meta, 'closed');
        if (!$saved) {
            return ['ok' => false, 'message' => 'Failed to record period closure'];
        }

        $this->audit->log('period_close', 'projection', 'closed', null, [
            'company_id' => $companyId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'meta' => $meta,
        ]);

        return ['ok' => true, 'message' => 'Period closed', 'meta' => $meta];
    }

    public function isPeriodClosed(int $companyId, string $periodStart, string $periodEnd): bool
    {
        return $this->repository->isPeriodClosed($companyId, $periodStart, $periodEnd);
    }

    /**
     * Advisory check for pipeline hook — does not block legacy writes.
     */
    public function wouldBlockPosting(int $companyId, string $entryDate): bool
    {
        $ps = date('Y-m-01', strtotime($entryDate) ?: time());
        $pe = date('Y-m-t', strtotime($entryDate) ?: time());

        return $this->repository->isPeriodClosed($companyId, $ps, $pe);
    }
}
