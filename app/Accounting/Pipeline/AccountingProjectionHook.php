<?php
declare(strict_types=1);

namespace App\Accounting\Pipeline;

use App\Accounting\Closing\AccountingPeriodCloser;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Support\AccountingConfig;

/**
 * Post-processing hook after successful event pipeline (async-safe).
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

        $companyId = (int) ($event['company_id'] ?? 0);
        $entryDate = (string) ($event['metadata']['entry_date'] ?? date('Y-m-d'));

        if ($companyId > 0 && $this->closer->wouldBlockPosting($companyId, $entryDate)) {
            error_log("AccountingProjectionHook: period closed for company {$companyId} date {$entryDate} — projection skipped");
            return;
        }

        try {
            $this->projections->afterEventProcessed($event, $eventUuid);
        } catch (\Throwable $e) {
            error_log('AccountingProjectionHook::afterEventProcessed: ' . $e->getMessage());
        }
    }
}
