<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Insights;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AiContextRepository;

final class AiConversationInsightsEngine implements EventSubscriberInterface
{
    private const TRIGGERS = [EventType::MESSAGE_RECEIVED, EventType::CONVERSATION_UPDATED];

    public function __construct(private readonly ?AiContextRepository $context = null)
    {
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if (!in_array($event->type, self::TRIGGERS, true)) {
            return;
        }
        $conversationId = (int) ($event->payload['conversation_id'] ?? 0);
        if ($conversationId < 1) {
            return;
        }
        $repo = $this->context ?? new AiContextRepository();
        $ctx = $repo->findByConversation($event->tenantId, $conversationId);
        $risk = (float) ($ctx['risk_score'] ?? 0);
        EventBus::instance()->emit([
            'type' => EventType::AI_INSIGHT_CREATED,
            'tenant_id' => $event->tenantId,
            'payload' => [
                'conversation_id' => $conversationId,
                'sentiment' => $ctx['sentiment'] ?? null,
                'intent' => $ctx['intent'] ?? null,
                'risk_score' => $risk,
            ],
        ]);
        if ($risk >= 0.7) {
            EventBus::instance()->emit([
                'type' => EventType::AI_RISK_DETECTED,
                'tenant_id' => $event->tenantId,
                'payload' => ['conversation_id' => $conversationId, 'risk_score' => $risk],
            ]);
        }
    }
}
