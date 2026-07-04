<?php
declare(strict_types=1);

namespace App\Accounting\Pipeline;

use App\Accounting\Closing\AccountingPeriodCloser;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Support\AccountingConfig;

/**
 * Post-processing hook — async-safe, non-blocking, never throws to pipeline.
 */
final class AccountingProjectionHook
{
    public function __construct(
        private readonly AccountingProjectionEngine $projections = new AccountingProjectionEngine(),
        private readonly AccountingPeriodCloser $closer = new AccountingPeriodCloser(),
    ) {
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $resultData
     */
    public function afterEventProcessed(array $event, string $eventUuid, array $resultData): void
    {
        if (!AccountingConfig::projectionsEnabled()) {
            return;
        }

        try {
            $companyId = (int) ($event['company_id'] ?? 0);
            $branchId = array_key_exists('branch_id', $event) && $event['branch_id'] !== null
                ? (int) $event['branch_id']
                : null;
            $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));

            if ($companyId > 0 && $this->closer->shouldSkipProjection($companyId, $entryDate, $branchId)) {
                return;
            }

            $this->projections->afterEventProcessed($event, $eventUuid);
        } catch (\Throwable $e) {
            error_log('AccountingProjectionHook::afterEventProcessed (non-blocking): ' . $e->getMessage());
        }
    }
}
