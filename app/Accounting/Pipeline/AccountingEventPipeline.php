<?php
declare(strict_types=1);

namespace App\Accounting\Pipeline;

use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Core\AccountingGateway;
use App\Accounting\Core\AccountingIdempotency;
use App\Accounting\Core\AccountingResult;
use App\Accounting\EventStore\AccountingEventStore;
use App\Accounting\Pipeline\AccountingProjectionHook;
use App\Accounting\Support\AccountingConfig;

/**
 * Event-driven extension layer — wraps AccountingGateway without modifying it.
 */
final class AccountingEventPipeline
{
    public function __construct(
        private readonly AccountingGateway $gateway,
        private readonly AccountingEventStore $eventStore = new AccountingEventStore(),
        private readonly AccountingIdempotency $idempotency = new AccountingIdempotency(),
        private readonly AccountingAuditService $audit = new AccountingAuditService(),
        private readonly AccountingProjectionHook $projectionHook = new AccountingProjectionHook(),
    ) {
    }

    public static function isEnabled(): bool
    {
        return AccountingConfig::eventStoreEnabled();
    }

    /**
     * @param array<string, mixed> $event
     */
    public function post(array $event): AccountingResult
    {
        $eventUuid = $this->resolveEventUuid($event);
        $sourceSystem = (string) ($event['source_system'] ?? 'unknown');

        $event['metadata'] = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $event['metadata']['event_uuid'] = $eventUuid;

        $isReplay = !empty($event['metadata']['replay']);
        if (!$isReplay && $this->idempotency->wasProcessed($eventUuid)) {
            $this->audit->log('idempotency_skip', $sourceSystem, 'skipped', $eventUuid, [
                'reason' => 'event_uuid already processed',
            ]);

            return AccountingResult::ok([
                'mode' => 'idempotent_skip',
                'event_uuid' => $eventUuid,
            ], 'Event already processed (idempotency)');
        }

        if (self::isEnabled() && $this->eventStore->isAvailable()) {
            $this->eventStore->persistPending($event, $eventUuid);
            $this->audit->log('event_created', $sourceSystem, 'pending', $eventUuid, [
                'event_type' => $event['event_type'] ?? null,
            ]);
        }

        $this->audit->log('gateway_route', $sourceSystem, 'routing', $eventUuid, [
            'target_adapter' => $sourceSystem,
        ]);

        $result = $this->gateway->post($event);

        if ($result->success) {
            if (self::isEnabled() && $this->eventStore->isAvailable()) {
                $this->eventStore->markProcessed($eventUuid);
            }
            $this->idempotency->markProcessed($eventUuid, $sourceSystem, $result->data);
            $this->audit->log('adapter_executed', $sourceSystem, 'processed', $eventUuid, [
                'result' => $result->data,
                'message' => $result->message,
            ]);
            $this->projectionHook->afterEventProcessed($event, $eventUuid, $result->data);
        } else {
            if (self::isEnabled() && $this->eventStore->isAvailable()) {
                $this->eventStore->markFailed($eventUuid);
            }
            $this->audit->log('adapter_executed', $sourceSystem, 'failed', $eventUuid, [
                'message' => $result->message,
                'data' => $result->data,
            ]);
        }

        return new AccountingResult(
            $result->success,
            $result->message,
            array_merge($result->data, ['event_uuid' => $eventUuid])
        );
    }

    /**
     * @param array<string, mixed> $event
     */
    private function resolveEventUuid(array $event): string
    {
        $meta = $event['metadata'] ?? [];
        if (is_array($meta) && !empty($meta['event_uuid']) && is_string($meta['event_uuid'])) {
            return $meta['event_uuid'];
        }

        return $this->generateUuid();
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
