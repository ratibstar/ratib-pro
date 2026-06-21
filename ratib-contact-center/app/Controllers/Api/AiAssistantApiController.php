<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Events\RealtimeEvent;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\AI\Assistant\AiAssistantEngine;
use Ratib\ContactCenter\App\Infrastructure\Ticket\TicketGateway;

/**
 * AI Copilot API — read context + agent-confirmed advisory actions.
 */
final class AiAssistantApiController
{
    private AiAssistantEngine $engine;

    public function __construct(?AiAssistantEngine $engine = null)
    {
        RealtimeOrchestrator::boot();
        $this->engine = $engine ?? new AiAssistantEngine(EventBus::instance());
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $action = (string) ($_GET['action'] ?? '');
            $body = $this->parseJsonBody();
            $input = array_merge($body, $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[RCC AiAssistantApi] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return $this->error('tenant_id required');
        }
        TenantContext::set($tenantId);

        $conversationId = (int) ($input['conversation_id'] ?? 0);
        if ($conversationId < 1 && $action !== 'health') {
            return $this->error('conversation_id required');
        }

        switch ($action) {
            case 'context':
                $ctx = $this->engine->contextForConversation($tenantId, $conversationId);
                if ($ctx === null) {
                    $ctx = $this->engine->processEvent(RealtimeEvent::fromNormalized([
                        'event_uuid' => bin2hex(random_bytes(16)),
                        'type' => EventType::CONVERSATION_UPDATED,
                        'tenant_id' => $tenantId,
                        'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                        'payload' => ['conversation_id' => $conversationId],
                        'agent_id' => isset($input['agent_id']) ? (int) $input['agent_id'] : null,
                    ]));
                }
                return ['ok' => true, 'ai_context' => $ctx];

            case 'create_ticket':
                $subject = (string) ($input['subject'] ?? 'Agent-requested ticket');
                $description = (string) ($input['description'] ?? '');
                $callId = isset($input['call_id']) ? (int) $input['call_id'] : null;
                $ticketId = (new TicketGateway())->createFromAssistant(
                    $tenantId,
                    $conversationId,
                    $callId,
                    $subject,
                    $description !== '' ? $description : 'Created from AI Copilot panel',
                    ['source' => 'agent_confirmed', 'auto_created' => false],
                    (string) ($input['priority'] ?? 'normal')
                );
                EventBus::instance()->emit([
                    'type' => EventType::AI_TICKET_CREATED,
                    'tenant_id' => $tenantId,
                    'agent_id' => isset($input['agent_id']) ? (int) $input['agent_id'] : null,
                    'payload' => [
                        'conversation_id' => $conversationId,
                        'ticket_id' => $ticketId,
                        'auto_created' => false,
                    ],
                ]);
                return ['ok' => true, 'ticket_id' => $ticketId];

            case 'health':
                return ['ok' => true, 'service' => 'rcc-ai-assistant'];

            default:
                return $this->error('Unknown action: ' . $action);
        }
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
