<?php
declare(strict_types=1);

namespace App\Accounting\Replay;

use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Core\AccountingIdempotency;
use App\Accounting\EventStore\AccountingEventStore;
use App\Accounting\Pipeline\AccountingEventPipeline;
use App\Accounting\Support\AccountingConfig;
use App\Accounting\Support\AccountingGatewayBootstrap;

/**
 * Admin-only replay tool — reprocesses immutable events from accounting_events.
 */
final class AccountingReplayEngine
{
    public function __construct(
        private readonly AccountingEventStore $eventStore = new AccountingEventStore(),
        private readonly AccountingIdempotency $idempotency = new AccountingIdempotency(),
        private readonly AccountingAuditService $audit = new AccountingAuditService(),
    ) {
    }

    /**
     * @param array<string, mixed> $filters source_system, event_type, company_id, from_date, to_date, status, force
     */
    public function replay(array $filters): ReplayResult
    {
        if (!AccountingConfig::replayEnabled()) {
            return new ReplayResult(0, 0, 0, 0, ['Replay is disabled (ACCOUNTING_REPLAY_ENABLED)']);
        }

        if (!$this->eventStore->isAvailable()) {
            return new ReplayResult(0, 0, 0, 0, ['accounting_events table not available']);
        }

        $force = !empty($filters['force']);
        unset($filters['force']);

        $events = $this->eventStore->fetchForReplay($filters);
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $pipeline = new AccountingEventPipeline(AccountingGatewayBootstrap::gateway());

        foreach ($events as $stored) {
            if (!$force && $stored->status === 'processed') {
                $skipped++;
                continue;
            }

            if ($force) {
                $this->idempotency->clear($stored->eventUuid);
            }

            $payload = $stored->payload;
            $payload['metadata'] = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
            $payload['metadata']['event_uuid'] = $stored->eventUuid;
            $payload['metadata']['replay'] = true;

            $this->audit->log('replay_start', $stored->sourceSystem, 'replay', $stored->eventUuid, [
                'stored_status' => $stored->status,
                'force' => $force,
            ]);

            $result = $pipeline->post($payload);

            if ($result->success) {
                $processed++;
                $this->audit->log('replay_complete', $stored->sourceSystem, 'processed', $stored->eventUuid, [
                    'result' => $result->data,
                ]);
            } else {
                $failed++;
                $errors[] = $stored->eventUuid . ': ' . ($result->message ?? 'failed');
                $this->audit->log('replay_complete', $stored->sourceSystem, 'failed', $stored->eventUuid, [
                    'message' => $result->message,
                ]);
            }
        }

        return new ReplayResult(count($events), $processed, $skipped, $failed, $errors);
    }
}
