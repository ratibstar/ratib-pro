<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Drift\DriftReport;
use App\Accounting\Support\AccountingConfig;

/**
 * Produces audit-grade AuditEvidencePack from drift + reconciliation artifacts.
 */
final class AccountingAuditCertificationEngine
{
    public function __construct(
        private readonly IntegrityRepository $repository = new IntegrityRepository(),
        private readonly AccountingReconciliationEngine $reconciliation = new AccountingReconciliationEngine(),
    ) {
    }

    public function isEnabled(): bool
    {
        return AccountingConfig::auditCertificationEnabled();
    }

    /**
     * @param array<string, mixed> $params company_id, branch_id?, period_from, period_to
     */
    public function certify(?DriftReport $drift, array $params, ?ReconciliationReport $reconciliationReport = null): AuditEvidencePack
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));

        if ($reconciliationReport === null && $drift !== null) {
            $reconciliationReport = $this->reconciliation->reconcileFromDrift($drift, $params);
        }

        $reconSummary = $reconciliationReport !== null
            ? $reconciliationReport->toArray()
            : ['status' => 'no_reconciliation'];

        $driftHistory = $this->repository->fetchReconciliationHistory($companyId, $periodFrom, $periodTo, $branchId);
        if ($drift !== null) {
            array_unshift($driftHistory, [
                'source' => 'current_drift_report',
                'drift_report_id' => $drift->reportId,
                'payload' => $drift->toArray(),
            ]);
        }

        $correctionLog = $this->repository->fetchCorrectionLog($companyId, $periodFrom, $periodTo);
        $snapshotHashes = $this->repository->computeSnapshotHashes($companyId, $periodFrom, $periodTo, $branchId);
        $lockedPeriods = $this->repository->fetchLockedPeriods($companyId, $branchId);

        $unresolved = $reconciliationReport !== null ? $reconciliationReport->driftItems : [];

        $payload = [
            'reconciliation_summary' => $reconSummary,
            'unresolved_drift' => $unresolved,
            'drift_history' => $driftHistory,
            'correction_log' => $correctionLog,
            'snapshot_hashes' => $snapshotHashes,
            'locked_periods' => $lockedPeriods,
            'certified_at' => date('c'),
        ];

        $certHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');

        $packId = null;
        if ($this->isEnabled()) {
            $packId = $this->repository->saveAuditEvidencePack(
                $companyId,
                $periodFrom,
                $periodTo,
                $payload,
                $certHash,
                $branchId
            );
        }

        return new AuditEvidencePack(
            companyId: $companyId,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            reconciliationSummary: $reconSummary,
            driftHistory: $driftHistory,
            correctionLog: $correctionLog,
            snapshotHashes: $snapshotHashes,
            lockedPeriods: $lockedPeriods,
            certificationHash: $certHash,
            branchId: $branchId,
            packId: $packId,
        );
    }
}
