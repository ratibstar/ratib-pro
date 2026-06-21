<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Assistant;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Domain\AI\Actions\NextBestActionEngine;
use Ratib\ContactCenter\App\Domain\AI\Intent\IntentDetector;
use Ratib\ContactCenter\App\Domain\AI\Reply\ReplySuggestionEngine;
use Ratib\ContactCenter\App\Domain\AI\Sentiment\SentimentAnalyzer;
use Ratib\ContactCenter\App\Domain\AI\Summary\ConversationSummarizer;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AiContextRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\ConversationRepository;
use Ratib\ContactCenter\App\Infrastructure\Ticket\TicketGateway;

/**
 * AI Copilot orchestrator — listens to EventBus, stores advisory context, emits AI_* events.
 * Does NOT override routing or block agent actions.
 */
final class AiAssistantEngine implements EventSubscriberInterface
{
    private const TRIGGER_TYPES = [
        EventType::MESSAGE_RECEIVED,
        EventType::MESSAGE_SENT,
        EventType::CALL_CONNECTED,
        EventType::CALL_ENDED,
        EventType::CONVERSATION_CREATED,
        EventType::CONVERSATION_UPDATED,
    ];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly ?EventBus $eventBus = null,
        private readonly ?ConversationSummarizer $summarizer = null,
        private readonly ?SentimentAnalyzer $sentimentAnalyzer = null,
        private readonly ?IntentDetector $intentDetector = null,
        private readonly ?NextBestActionEngine $actionEngine = null,
        private readonly ?ReplySuggestionEngine $replyEngine = null,
        private readonly ?AiContextRepository $contextRepo = null,
        private readonly ?ConversationRepository $conversations = null,
        private readonly ?TicketGateway $ticketGateway = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    public function onEvent(RealtimeEvent $event): void
    {
        if (!($this->config['enabled'] ?? true)) {
            return;
        }
        if (!in_array($event->type, self::TRIGGER_TYPES, true)) {
            return;
        }

        try {
            $this->processEvent($event);
        } catch (\Throwable $e) {
            error_log('[RCC AiAssistant] ' . $event->type . ': ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function processEvent(RealtimeEvent $event): ?array
    {
        $conversationId = $this->resolveConversationId($event);
        if ($conversationId === null) {
            return null;
        }

        $tenantId = $event->tenantId;
        $conversation = ($this->conversations ?? new ConversationRepository())
            ->findById($tenantId, $conversationId);
        if ($conversation === null) {
            return null;
        }

        $messages = ($this->summarizer ?? new ConversationSummarizer())->loadMessages($tenantId, $conversationId);
        $texts = $this->extractTexts($messages, $event);

        $sentiment = ($this->sentimentAnalyzer ?? new SentimentAnalyzer())->analyze($texts);
        $intent = ($this->intentDetector ?? new IntentDetector())->detect($texts);

        $slaStatus = (string) ($conversation['sla_status'] ?? 'green');
        $erpFlags = $this->erpFlagsFromConversation($conversation);

        $risk = ($this->actionEngine ?? new NextBestActionEngine())->computeRisk(
            $sentiment['label'],
            (float) $sentiment['score'],
            $intent['intent'],
            $slaStatus
        );

        $summaryLive = ($this->summarizer ?? new ConversationSummarizer())->summarizeLive($messages, $conversation);
        $summaryFinal = null;
        if ($event->type === EventType::CALL_ENDED) {
            $summaryFinal = ($this->summarizer ?? new ConversationSummarizer())->summarizeFinal($messages, $conversation);
        }

        $action = ($this->actionEngine ?? new NextBestActionEngine())->recommend([
            'sentiment' => $sentiment['label'],
            'sentiment_score' => $sentiment['score'],
            'intent' => $intent['intent'],
            'risk_score' => $risk['risk_score'],
            'sla_status' => $slaStatus,
            'erp_flags' => $erpFlags,
        ]);

        $channel = (string) ($conversation['last_channel'] ?? 'chat');
        $reply = ($this->replyEngine ?? new ReplySuggestionEngine())->suggest(
            $intent['intent'],
            $channel,
            $conversation
        );

        $existing = ($this->contextRepo ?? new AiContextRepository())->findByConversation($tenantId, $conversationId);
        $ticketId = $existing['ticket_id'] ?? null;

        $stored = ($this->contextRepo ?? new AiContextRepository())->upsert($tenantId, $conversationId, [
            'sentiment' => $sentiment['label'],
            'sentiment_score' => $sentiment['score'],
            'intent' => $intent['intent'],
            'intent_confidence' => $intent['confidence'],
            'summary_live' => $summaryLive,
            'summary_final' => $summaryFinal,
            'risk_score' => $risk['risk_score'],
            'recommended_action' => $action['action'],
            'suggested_reply' => $reply['suggested_reply'],
            'suggestions_json' => [
                'sentiment' => $sentiment,
                'intent' => $intent,
                'actions' => $action['actions'] ?? [],
                'replies' => $reply,
                'summary_live' => $summaryLive,
            ],
            'ticket_id' => $ticketId,
        ]);

        $this->emitAiUpdates($event, $conversationId, $stored, $sentiment, $intent, $action, $reply, $summaryLive, $summaryFinal);

        if ($ticketId === null) {
            $autoTicketId = $this->maybeAutoCreateTicket(
                $tenantId,
                $conversationId,
                $conversation,
                $sentiment['label'],
                $intent['intent'],
                $slaStatus,
                $summaryLive
            );
            if ($autoTicketId !== null) {
                $stored = ($this->contextRepo ?? new AiContextRepository())->upsert($tenantId, $conversationId, [
                    'ticket_id' => $autoTicketId,
                ]);
            }
        }

        $this->emitAssistantBundle($event, $conversationId, $stored);

        return $stored;
    }

    /** @return array<string, mixed>|null */
    public function contextForConversation(int $tenantId, int $conversationId): ?array
    {
        return ($this->contextRepo ?? new AiContextRepository())->findByConversation($tenantId, $conversationId);
    }

    private function resolveConversationId(RealtimeEvent $event): ?int
    {
        $fromPayload = (int) ($event->payload['conversation_id'] ?? 0);
        if ($fromPayload > 0) {
            return $fromPayload;
        }
        $conv = $event->payload['conversation'] ?? null;
        if (is_array($conv) && !empty($conv['conversation_id'])) {
            return (int) $conv['conversation_id'];
        }
        if ($event->callId !== null) {
            $found = ($this->conversations ?? new ConversationRepository())
                ->findByCallId($event->tenantId, $event->callId);
            if ($found !== null) {
                return (int) $found['conversation_id'];
            }
        }
        return null;
    }

    /** @param list<array<string, mixed>> $messages */
    private function extractTexts(array $messages, RealtimeEvent $event): array
    {
        $texts = [];
        foreach ($messages as $msg) {
            $t = trim((string) ($msg['message'] ?? ''));
            if ($t !== '') {
                $texts[] = $t;
            }
        }
        $incoming = (string) ($event->payload['message']['message'] ?? $event->payload['message'] ?? '');
        if ($incoming !== '' && !in_array($incoming, $texts, true)) {
            $texts[] = $incoming;
        }
        return $texts;
    }

    /** @param array<string, mixed> $conversation */
    private function erpFlagsFromConversation(array $conversation): array
    {
        $meta = is_array($conversation['metadata'] ?? null) ? $conversation['metadata'] : [];
        $erp = is_array($meta['erp_customer'] ?? null) ? $meta['erp_customer'] : [];
        $contact = is_array($erp['contact'] ?? null) ? $erp['contact'] : [];
        return [
            'vip_customer' => ($contact['contact_type'] ?? '') === 'vip',
            'high_value_company' => ($erp['company']['tier'] ?? '') === 'enterprise',
        ];
    }

    /** @param array<string, mixed> $stored */
    private function emitAiUpdates(
        RealtimeEvent $event,
        int $conversationId,
        array $stored,
        array $sentiment,
        array $intent,
        array $action,
        array $reply,
        string $summaryLive,
        ?string $summaryFinal
    ): void {
        $bus = $this->eventBus ?? EventBus::instance();
        $base = [
            'tenant_id' => $event->tenantId,
            'agent_id' => $event->agentId,
            'call_id' => $event->callId,
            'payload' => ['conversation_id' => $conversationId],
        ];

        $bus->emit(array_merge($base, [
            'type' => EventType::AI_SUMMARY_UPDATED,
            'payload' => [
                'conversation_id' => $conversationId,
                'summary_live' => $summaryLive,
                'summary_final' => $summaryFinal,
            ],
        ]));

        $bus->emit(array_merge($base, [
            'type' => EventType::AI_SENTIMENT_UPDATED,
            'payload' => ['conversation_id' => $conversationId, 'sentiment' => $sentiment],
        ]));

        $bus->emit(array_merge($base, [
            'type' => EventType::AI_INTENT_DETECTED,
            'payload' => ['conversation_id' => $conversationId, 'intent' => $intent],
        ]));

        $bus->emit(array_merge($base, [
            'type' => EventType::AI_RECOMMENDATION_READY,
            'payload' => [
                'conversation_id' => $conversationId,
                'recommended_action' => $action['action'],
                'actions' => $action['actions'] ?? [],
                'risk_score' => $stored['risk_score'] ?? null,
            ],
        ]));

        $bus->emit(array_merge($base, [
            'type' => EventType::AI_REPLY_SUGGESTED,
            'payload' => [
                'conversation_id' => $conversationId,
                'suggested_reply' => $reply['suggested_reply'],
                'by_channel' => $reply['by_channel'] ?? [],
            ],
        ]));
    }

    /** @param array<string, mixed> $stored */
    private function emitAssistantBundle(RealtimeEvent $event, int $conversationId, array $stored): void
    {
        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::AI_ASSISTANT_UPDATE,
            'tenant_id' => $event->tenantId,
            'agent_id' => $event->agentId,
            'call_id' => $event->callId,
            'payload' => [
                'conversation_id' => $conversationId,
                'ai_context' => $stored,
            ],
        ]);
    }

    /** @param array<string, mixed> $conversation */
    private function maybeAutoCreateTicket(
        int $tenantId,
        int $conversationId,
        array $conversation,
        string $sentimentLabel,
        string $intent,
        string $slaStatus,
        string $summary
    ): ?int {
        $rules = $this->config['auto_ticket'] ?? [];
        if (!($rules['enabled'] ?? false)) {
            return null;
        }

        $reqSent = $rules['require_sentiment'] ?? [];
        $reqIntent = $rules['require_intent'] ?? [];
        $reqSla = $rules['require_sla'] ?? [];

        if (is_array($reqSent) && !in_array($sentimentLabel, $reqSent, true)) {
            return null;
        }
        if (is_array($reqIntent) && !in_array($intent, $reqIntent, true)) {
            return null;
        }
        if (is_array($reqSla) && !in_array($slaStatus, $reqSla, true)) {
            return null;
        }

        $subject = 'AI escalated: ' . str_replace('_', ' ', $intent);
        try {
            $ticketId = ($this->ticketGateway ?? new TicketGateway())->createFromAssistant(
                $tenantId,
                $conversationId,
                $conversation['call_id'] ?? null,
                $subject,
                $summary,
                [
                    'sentiment' => $sentimentLabel,
                    'intent' => $intent,
                    'sla_status' => $slaStatus,
                    'auto_created' => true,
                    'channel' => (string) ($conversation['last_channel'] ?? 'web_chat'),
                ],
                'high'
            );
        } catch (\Throwable $e) {
            error_log('[RCC AiAssistant] auto ticket: ' . $e->getMessage());
            return null;
        }

        ($this->eventBus ?? EventBus::instance())->emit([
            'type' => EventType::AI_TICKET_CREATED,
            'tenant_id' => $tenantId,
            'agent_id' => $conversation['assigned_agent_id'] ?? null,
            'call_id' => $conversation['call_id'] ?? null,
            'payload' => [
                'conversation_id' => $conversationId,
                'ticket_id' => $ticketId,
                'auto_created' => true,
            ],
        ]);

        return $ticketId;
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/assistant.php';
        return is_file($path) ? (require $path) : [];
    }
}
