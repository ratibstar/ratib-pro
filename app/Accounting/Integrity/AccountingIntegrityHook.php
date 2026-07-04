<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Drift\AccountingDriftDetector;
use App\Accounting\Support\AccountingConfig;

/**
 * Phase 5 follow-up — runs after Phase 4 projection hook completes.
 * Async-safe, non-blocking, never throws to pipeline.
 */
final class AccountingIntegrityHook
{
    public function __construct(
        private readonly AccountingDriftDetector $driftDetector = new AccountingDriftDetector(),
        private readonly AccountingReconciliationEngine $reconciliation = new AccountingReconciliationEngine(),
        private readonly AccountingConsolidationEngine $consolidation = new AccountingConsolidationEngine(),
        private readonly AccountingAuditCertificationEngine $certification = new AccountingAuditCertificationEngine(),
    ) {
    }

    /**
     * Triggered after projection (and optionally consolidation) — mirrors AccountingProjectionHook signature.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $resultData
     */
    public function afterProjectionCompleted(array $event, string $eventUuid, array $resultData): void
    {
        if (!AccountingConfig::integrityEnabled()) {
            return;
        }

        try {
            $companyId = (int) ($event['company_id'] ?? 0);
            if ($companyId < 1) {
                return;
            }

            $branchId = array_key_exists('branch_id', $event) && $event['branch_id'] !== null
                ? (int) $event['branch_id']
                : null;
            $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));
            $periodFrom = date('Y-m-01', strtotime($entryDate) ?: time());
            $periodTo = date('Y-m-t', strtotime($entryDate) ?: time());

            $params = [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
            ];

            if (AccountingConfig::consolidationEnabled()) {
                $this->consolidation->runConsolidation($params);
            }

            if (!AccountingConfig::driftDetectionEnabled()) {
                return;
            }

            $drift = $this->driftDetector->detectDrift($params);
            $recon = $this->reconciliation->reconcileFromDrift($drift, $params);

            if (AccountingConfig::auditCertificationEnabled() && ($drift->hasDrift() || $recon->hasUnresolvedDrift())) {
                $this->certification->certify($drift, $params, $recon);
            }
        } catch (\Throwable $e) {
            error_log('AccountingIntegrityHook::afterProjectionCompleted (non-blocking): ' . $e->getMessage());
        }
    }
}
