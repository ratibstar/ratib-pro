<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Insights;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Domain\AI\Summary\ConversationSummarizer;

/** Call summaries, risk, upsell/churn signals from ended calls. */
final class AiCallInsightsEngine implements EventSubscriberInterface
{
    public function __construct(private readonly ?ConversationSummarizer $summarizer = null)
    {
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if ($event->type !== EventType::CALL_ENDED) {
            return;
        }
        $payload = $event->payload;
        $insights = [
            'summary' => 'Call completed — duration ' . (int) ($payload['duration_seconds'] ?? 0) . 's',
            'risk_level' => $this->riskLevel($payload),
            'opportunities' => $this->detectOpportunities($payload),
        ];
        EventBus::instance()->emit([
            'type' => EventType::AI_INSIGHT_CREATED,
            'tenant_id' => $event->tenantId,
            'payload' => array_merge($insights, ['call_id' => $payload['call_id'] ?? null]),
        ]);
        if ($insights['risk_level'] === 'high') {
            EventBus::instance()->emit([
                'type' => EventType::AI_RISK_DETECTED,
                'tenant_id' => $event->tenantId,
                'payload' => ['call_id' => $payload['call_id'] ?? null, 'risk' => 'churn'],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function riskLevel(array $payload): string
    {
        if (!empty($payload['sentiment']) && $payload['sentiment'] === 'negative') {
            return 'high';
        }
        if ((int) ($payload['duration_seconds'] ?? 0) < 30) {
            return 'medium';
        }
        return 'low';
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private function detectOpportunities(array $payload): array
    {
        $text = strtolower((string) ($payload['transcript'] ?? $payload['summary'] ?? ''));
        $ops = [];
        if (str_contains($text, 'upgrade') || str_contains($text, 'premium')) {
            $ops[] = 'upsell';
        }
        if (str_contains($text, 'cancel') || str_contains($text, 'leave')) {
            $ops[] = 'retention';
        }
        if (str_contains($text, 'buy') || str_contains($text, 'purchase')) {
            $ops[] = 'sales';
        }
        return $ops;
    }
}
