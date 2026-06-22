<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Insights;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AiContextRepository;

/** Auto QA scoring and compliance hints from call/conversation events. */
final class AiQaEngine implements EventSubscriberInterface
{
    private const TRIGGERS = [EventType::CALL_ENDED, EventType::CONVERSATION_UPDATED];

    public function __construct(private readonly ?AiContextRepository $context = null)
    {
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if (!in_array($event->type, self::TRIGGERS, true)) {
            return;
        }
        $payload = $event->payload;
        $score = $this->estimateScore($payload);
        EventBus::instance()->emit([
            'type' => EventType::AI_QA_COMPLETED,
            'tenant_id' => $event->tenantId,
            'payload' => [
                'score' => $score,
                'source_event' => $event->type,
                'conversation_id' => $payload['conversation_id'] ?? null,
                'call_id' => $payload['call_id'] ?? null,
            ],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function estimateScore(array $payload): float
    {
        $base = 75.0;
        if (!empty($payload['duration_seconds']) && (int) $payload['duration_seconds'] > 300) {
            $base -= 5;
        }
        if (!empty($payload['sentiment']) && $payload['sentiment'] === 'negative') {
            $base -= 15;
        }
        return max(0, min(100, $base));
    }
}
