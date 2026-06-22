<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiPbxCommandGateway;

/**
 * Delivers queued calls to selected agents via AMI Originate (real SIP ring).
 */
final class QueueDeliveryService implements EventSubscriberInterface
{
    private static bool $registered = false;

    private AmiPbxCommandGateway $pbx;

    public function __construct(?AmiPbxCommandGateway $pbx = null)
    {
        $this->pbx = $pbx ?? new AmiPbxCommandGateway();
    }

    public static function registerSubscriber(): void
    {
        if (self::$registered) {
            return;
        }
        EventBus::instance()->subscribe(new self());
        self::$registered = true;
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if ($event->type !== EventType::CALL_ASSIGNED) {
            return;
        }

        $agentId = $event->agentId;
        $callId = $event->callId;
        $queueId = $event->queueId;
        if ($agentId === null || $agentId < 1 || $callId === null || $callId < 1) {
            return;
        }

        $channelId = (string) ($event->payload['channel_id'] ?? '');
        if ($channelId === '') {
            $channelId = $this->channelForCall($event->tenantId, $callId);
        }
        if ($channelId === '') {
            error_log('[RCC QueueDelivery] No channel for call ' . $callId);
            return;
        }

        $extension = $this->agentExtension($event->tenantId, $agentId);
        if ($extension === '') {
            error_log('[RCC QueueDelivery] No extension for agent ' . $agentId);
            return;
        }

        try {
            $this->pbx->originateToAgent(
                $event->tenantId,
                $agentId,
                $extension,
                $channelId,
                $callId,
                (int) ($queueId ?? 0)
            );

            EventBus::instance()->emit([
                'type' => EventType::QUEUE_ASSIGNED,
                'tenant_id' => $event->tenantId,
                'agent_id' => $agentId,
                'call_id' => $callId,
                'queue_id' => $queueId,
                'payload' => [
                    'channel_id' => $channelId,
                    'extension' => $extension,
                    'delivery' => 'ami_originate',
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC QueueDelivery] ' . $e->getMessage());
        }
    }

    private function channelForCall(int $tenantId, int $callId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT channel_id FROM rcc_calls WHERE id = :cid AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['cid' => $callId, 'tid' => $tenantId]);
        $ch = $stmt->fetchColumn();
        return $ch !== false ? (string) $ch : '';
    }

    private function agentExtension(int $tenantId, int $agentId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT extension FROM rcc_agents WHERE tenant_id = :tid AND id = :aid AND status = \'active\' LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        $ext = $stmt->fetchColumn();
        return $ext !== false ? (string) $ext : '';
    }
}
