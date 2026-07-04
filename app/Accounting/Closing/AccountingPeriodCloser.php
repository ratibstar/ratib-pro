<?php
declare(strict_types=1);

namespace App\Accounting\Closing;

use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Projections\ProjectionRepository;

/**
 * Soft + hard period closing. Does NOT block AccountingGateway pipeline.
 */
final class AccountingPeriodCloser
{
    public const STATUS_SOFT = 'soft_closed';
    public const STATUS_HARD = 'hard_closed';
    public const STATUS_REOPENED = 'reopened';

    public function __construct(
        private readonly ProjectionRepository $repository = new ProjectionRepository(),
        private readonly AccountingProjectionEngine $projections = new AccountingProjectionEngine(),
        private readonly AccountingConsolidationEngine $consolidation = new AccountingConsolidationEngine(),
        private readonly AccountingAuditService $audit = new AccountingAuditService(),
    ) {
    }

    /**
     * Soft close — snapshots generated; postings still allowed (pipeline never blocked).
     *
     * @return array{ok:bool, message:string, meta?:array<string,mixed>}
     */
    public function softClosePeriod(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        return $this->closePeriod($companyId, $periodFrom, $periodTo, self::STATUS_SOFT, $branchId);
    }

    /**
     * Hard close — snapshots immutable; external systems may enforce posting block.
     *
     * @return array{ok:bool, message:string, meta?:array<string,mixed>}
     */
    public function hardClosePeriod(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): array
    {
        return $this->closePeriod($companyId, $periodFrom, $periodTo, self::STATUS_HARD, $branchId);
    }

    /**
     * @return array{ok:bool, message:string, meta?:array<string,mixed>}
     */
    public function closePeriod(
        int $companyId,
        string $periodFrom,
        string $periodTo,
        string $mode = self::STATUS_SOFT,
        ?int $branchId = null
    ): array {
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'Invalid company_id'];
        }

        if ($mode === self::STATUS_HARD && $this->repository->isPeriodHardClosed($companyId, $periodFrom, $periodTo, $branchId)) {
            return ['ok' => false, 'message' => 'Period already hard closed'];
        }

        $snapshotCounts = $this->projections->projectPeriod([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        $consolidation = $this->consolidation->runConsolidation([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        $payload = [
            'mode' => $mode,
            'snapshots' => $snapshotCounts,
            'consolidation' => $consolidation,
            'closed_at' => date('c'),
        ];

        $saved = $this->repository->recordPeriodClosure($companyId, $periodFrom, $periodTo, $payload, $mode, $branchId);
        if (!$saved) {
            return ['ok' => false, 'message' => 'Failed to record period closure'];
        }

        $this->audit->log('period_close', 'projection', $mode, null, [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'payload' => $payload,
        ]);

        return ['ok' => true, 'message' => "Period {$mode}", 'meta' => $payload];
    }

    public function isPeriodHardClosed(int $companyId, string $periodFrom, string $periodTo, ?int $branchId = null): bool
    {
        return $this->repository->isPeriodHardClosed($companyId, $periodFrom, $periodTo, $branchId);
    }

    /**
     * Advisory only — pipeline MUST NOT be blocked (returns false always for hook).
     */
    public function wouldBlockPosting(int $companyId, string $entryDate, ?int $branchId = null): bool
    {
        return false;
    }

    /**
     * Whether snapshot projection should skip updates for this period.
     */
    public function shouldSkipProjection(int $companyId, string $entryDate, ?int $branchId = null): bool
    {
        $pf = date('Y-m-01', strtotime($entryDate) ?: time());
        $pt = date('Y-m-t', strtotime($entryDate) ?: time());

        return $this->repository->isPeriodHardClosed($companyId, $pf, $pt, $branchId);
    }
}
