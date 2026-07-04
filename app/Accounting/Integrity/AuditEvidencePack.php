<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Audit-grade certification bundle for external review.
 */
final class AuditEvidencePack
{
    /**
     * @param array<string, mixed> $reconciliationSummary
     * @param list<array<string, mixed>> $driftHistory
     * @param list<array<string, mixed>> $correctionLog
     * @param array<string, string> $snapshotHashes
     * @param list<array<string, mixed>> $lockedPeriods
     */
    public function __construct(
        public readonly int $companyId,
        public readonly string $periodFrom,
        public readonly string $periodTo,
        public readonly array $reconciliationSummary,
        public readonly array $driftHistory,
        public readonly array $correctionLog,
        public readonly array $snapshotHashes,
        public readonly array $lockedPeriods,
        public readonly string $certificationHash,
        public readonly ?int $branchId = null,
        public readonly ?int $packId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pack_id' => $this->packId,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'certification_hash' => $this->certificationHash,
            'reconciliation_summary' => $this->reconciliationSummary,
            'drift_history' => $this->driftHistory,
            'correction_log' => $this->correctionLog,
            'snapshot_hashes' => $this->snapshotHashes,
            'locked_periods' => $this->lockedPeriods,
        ];
    }
}
