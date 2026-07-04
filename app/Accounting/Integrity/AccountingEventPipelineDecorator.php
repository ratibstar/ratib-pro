<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

use App\Accounting\Core\AccountingResult;
use App\Accounting\Pipeline\AccountingEventPipeline;
use App\Accounting\Support\AccountingConfig;

/**
 * Wraps Phase 3 pipeline without modifying it — runs Phase 5 integrity after inner post (Phase 4 runs inside pipeline).
 */
final class AccountingEventPipelineDecorator
{
    public function __construct(
        private readonly AccountingEventPipeline $inner,
        private readonly AccountingIntegrityHook $integrityHook = new AccountingIntegrityHook(),
    ) {
    }

    public static function shouldUse(): bool
    {
        return AccountingConfig::integrityEnabled();
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        $result = $this->inner->post($event);

        if (!$result->success || !self::shouldUse()) {
            return $result;
        }

        $eventUuid = (string) ($result->data['event_uuid'] ?? $event['metadata']['event_uuid'] ?? '');
        if ($eventUuid === '') {
            return $result;
        }

        try {
            $this->integrityHook->afterProjectionCompleted($event, $eventUuid, $result->data);
        } catch (\Throwable $e) {
            error_log('AccountingEventPipelineDecorator post-chain (non-blocking): ' . $e->getMessage());
        }

        return $result;
    }

    public static function isEnabled(): bool
    {
        return AccountingEventPipeline::isEnabled();
    }
}
