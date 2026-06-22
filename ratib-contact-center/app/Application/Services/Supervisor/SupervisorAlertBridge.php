<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Supervisor;

use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;

/**
 * Converts SLA/queue events into persisted supervisor alerts + SUPERVISOR_ALERT_RAISED.
 */
final class SupervisorAlertBridge implements EventSubscriberInterface
{
    public function __construct(
        private readonly SupervisorAlertService $alerts = new SupervisorAlertService()
    ) {
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if ($event->type === EventType::SLA_ALERT) {
            $payload = $event->payload;
            $level = (string) ($payload['level'] ?? 'yellow');
            if ($level !== 'red') {
                return;
            }
            $this->alerts->raise($event->tenantId, [
                'alert_type' => 'sla_breach',
                'severity' => 'critical',
                'title' => 'SLA breach on queue',
                'title_ar' => 'خرق اتفاقية مستوى الخدمة في الطابور',
                'message' => sprintf(
                    'Longest wait %ds (target %ds), %d waiting, %d agents available',
                    (int) ($payload['longest_wait_seconds'] ?? 0),
                    (int) ($payload['sla_target_seconds'] ?? 0),
                    (int) ($payload['waiting_count'] ?? 0),
                    (int) ($payload['available_agents'] ?? 0)
                ),
                'source_event' => EventType::SLA_ALERT,
                'queue_id' => $event->queueId,
                'payload' => $payload,
            ]);
        }

        if ($event->type === EventType::QUEUE_SNAPSHOT) {
            $p = $event->payload;
            $waiting = (int) ($p['waiting_count'] ?? 0);
            $available = (int) ($p['available_agents'] ?? 0);
            if ($waiting > 0 && $available === 0) {
                $this->alerts->raise($event->tenantId, [
                    'alert_type' => 'queue_no_agents',
                    'severity' => 'warning',
                    'title' => 'Calls waiting with no available agents',
                    'title_ar' => 'مكالمات منتظرة بدون وكلاء متاحين',
                    'message' => sprintf('%d waiting on queue %s', $waiting, (string) ($p['queue_code'] ?? '')),
                    'source_event' => EventType::QUEUE_SNAPSHOT,
                    'queue_id' => $event->queueId,
                    'payload' => $p,
                ]);
            }
        }
    }
}
