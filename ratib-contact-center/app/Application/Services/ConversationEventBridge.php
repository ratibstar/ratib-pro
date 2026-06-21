<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;
use Ratib\ContactCenter\App\Infrastructure\Channels\VoiceChannelAdapter;

/**
 * EventBus bridge — voice/IVR/routing events → unified conversations.
 */
final class ConversationEventBridge implements EventSubscriberInterface
{
    private ConversationEngine $engine;
    private VoiceChannelAdapter $voice;

    public function __construct(?ConversationEngine $engine = null)
    {
        $this->engine = $engine ?? new ConversationEngine();
        $this->voice = new VoiceChannelAdapter($this->engine);
    }

    public function onEvent(RealtimeEvent $event): void
    {
        try {
            match ($event->type) {
                EventType::CALL_INCOMING => $this->onCallIncoming($event),
                EventType::CALL_CONNECTED => $this->onCallConnected($event),
                EventType::CALL_ASSIGNED => $this->onCallAssigned($event),
                EventType::CALL_ENDED => $this->onCallEnded($event),
                EventType::IVR_STARTED => $this->onIvrStarted($event),
                default => null,
            };
        } catch (\Throwable $e) {
            error_log('[RCC ConversationBridge] ' . $event->type . ': ' . $e->getMessage());
        }
    }

    private function onCallIncoming(RealtimeEvent $event): void
    {
        if ($event->callId === null) {
            return;
        }
        $caller = (string) ($event->payload['caller_number'] ?? '');
        if ($caller === '') {
            return;
        }
        $this->voice->onIncoming($event->tenantId, $event->callId, $caller, $event->ivrSessionId);
    }

    private function onCallConnected(RealtimeEvent $event): void
    {
        if ($event->callId === null) {
            return;
        }
        $remote = (string) ($event->payload['remote_number'] ?? $event->payload['caller_number'] ?? '');
        $erp = is_array($event->payload['erp_customer'] ?? null) ? $event->payload['erp_customer'] : null;
        $this->voice->onConnected(
            $event->tenantId,
            $event->callId,
            $event->agentId,
            $remote !== '' ? $remote : null,
            $erp
        );
    }

    private function onCallAssigned(RealtimeEvent $event): void
    {
        if ($event->callId === null || $event->agentId === null) {
            return;
        }
        $decision = is_array($event->payload) ? $event->payload : [];
        $this->engine->assignFromRouting(
            $event->tenantId,
            $event->callId,
            $event->agentId,
            $event->queueId,
            $decision
        );
    }

    private function onCallEnded(RealtimeEvent $event): void
    {
        if ($event->callId === null) {
            return;
        }
        $conversation = (new \Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\ConversationRepository())
            ->findByCallId($event->tenantId, $event->callId);
        if ($conversation === null) {
            return;
        }
        $this->engine->markPending(
            $event->tenantId,
            (int) $conversation['conversation_id'],
            $event->agentId
        );
    }

    private function onIvrStarted(RealtimeEvent $event): void
    {
        if ($event->callId === null || $event->ivrSessionId === null) {
            return;
        }
        $this->engine->attachIvrSession(
            $event->tenantId,
            $event->callId,
            $event->ivrSessionId,
            $event->payload
        );
    }
}
