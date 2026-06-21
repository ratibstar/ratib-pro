<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\ErpBridge;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Core\TenantContext;

/**
 * ERP-linked activity stream — persists cross-system audit trail from realtime events.
 */
final class ErpActivityLogger implements EventSubscriberInterface
{
    /** @var list<string> */
    private array $trackedTypes;

    /** @param list<string>|null $trackedTypes */
    public function __construct(?array $trackedTypes = null)
    {
        $this->trackedTypes = $trackedTypes ?? [
            EventType::CALL_INCOMING,
            EventType::CALL_CONNECTED,
            EventType::CALL_ENDED,
            EventType::IVR_COMPLETED,
            EventType::QUEUE_JOINED,
            EventType::QUEUE_ASSIGNED,
            EventType::AGENT_LOGIN,
            EventType::AGENT_OFFLINE,
            EventType::SLA_ALERT,
        ];
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if (!in_array($event->type, $this->trackedTypes, true)) {
            return;
        }

        $summary = $this->buildSummary($event);
        $erpCompanyId = TenantContext::erpCompanyId() ?? ($event->payload['erp_company_id'] ?? null);

        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO rcc_erp_activity_log
                 (tenant_id, erp_company_id, event_uuid, activity_type, reference_type, reference_id, summary, payload, created_at)
                 VALUES (:tid, :erp, :uuid, :type, :ref_type, :ref_id, :summary, :payload, :ts)'
            );
            $stmt->execute([
                'tid' => $event->tenantId,
                'erp' => $erpCompanyId,
                'uuid' => $event->eventUuid,
                'type' => $event->type,
                'ref_type' => $this->referenceType($event),
                'ref_id' => $event->callId ?? $event->agentId ?? $event->queueId,
                'summary' => $summary,
                'payload' => $event->toJson(),
                'ts' => $event->timestamp,
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC ErpActivityLogger] ' . $e->getMessage());
        }

        if ($erpCompanyId !== null && (int) $erpCompanyId > 0) {
            ErpBridge::companyById((int) $erpCompanyId);
        }
    }

    private function buildSummary(RealtimeEvent $event): string
    {
        return match ($event->type) {
            EventType::CALL_INCOMING => 'Incoming call ' . ($event->payload['caller_number'] ?? ''),
            EventType::CALL_CONNECTED => 'Call connected agent #' . ($event->agentId ?? '?'),
            EventType::CALL_ENDED => 'Call ended #' . ($event->callId ?? '?'),
            EventType::IVR_COMPLETED => 'IVR completed session #' . ($event->ivrSessionId ?? '?'),
            EventType::QUEUE_JOINED => 'Caller joined queue #' . ($event->queueId ?? '?'),
            EventType::QUEUE_ASSIGNED => 'Queue assigned to agent #' . ($event->agentId ?? '?'),
            EventType::SLA_ALERT => 'SLA alert level ' . ($event->payload['level'] ?? 'unknown'),
            default => $event->type,
        };
    }

    private function referenceType(RealtimeEvent $event): ?string
    {
        if ($event->callId !== null) {
            return 'call';
        }
        if ($event->agentId !== null) {
            return 'agent';
        }
        if ($event->queueId !== null) {
            return 'queue';
        }
        if ($event->ivrSessionId !== null) {
            return 'ivr_session';
        }
        return null;
    }
}
