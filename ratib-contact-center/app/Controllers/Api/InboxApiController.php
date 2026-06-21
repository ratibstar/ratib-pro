<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\Conversation\ConversationEngine;
use Ratib\ContactCenter\App\Infrastructure\Channels\EmailChannelAdapter;
use Ratib\ContactCenter\App\Infrastructure\Channels\WebChatChannelAdapter;
use Ratib\ContactCenter\App\Infrastructure\Channels\WhatsAppChannelAdapter;

/**
 * Thin API for unified agent inbox — no business logic in controller.
 */
final class InboxApiController
{
    private ?ConversationEngine $engine = null;

    private function engine(bool $withRealtime = false): ConversationEngine
    {
        if ($this->engine === null) {
            if ($withRealtime) {
                RealtimeOrchestrator::boot();
            }
            $this->engine = new ConversationEngine(EventBus::instance());
        }
        return $this->engine;
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $action = (string) ($_GET['action'] ?? '');
            $body = $this->parseJsonBody();
            $input = array_merge($body, $_GET);
            $result = $this->handleAction($action, $input);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[RCC InboxApi] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Inbox request failed',
                'detail' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
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

        switch ($action) {
            case 'health':
                return ['ok' => true, 'service' => 'rcc-inbox', 'php' => PHP_VERSION];

            case 'inbox':
                $agentId = (int) ($input['agent_id'] ?? 0);
                if ($agentId < 1) {
                    return $this->error('agent_id required');
                }
                return ['ok' => true, 'conversations' => $this->engine()->inboxForAgent($tenantId, $agentId)];

            case 'thread':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                if ($conversationId < 1) {
                    return $this->error('conversation_id required');
                }
                return ['ok' => true] + $this->engine()->thread($tenantId, $conversationId);

            case 'send':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                $agentId = (int) ($input['agent_id'] ?? 0);
                $channel = (string) ($input['channel'] ?? 'chat');
                $message = (string) ($input['message'] ?? '');
                if ($conversationId < 1 || $agentId < 1 || $message === '') {
                    return $this->error('conversation_id, agent_id, message required');
                }
                $updated = $this->engine(true)->sendOutbound($tenantId, $conversationId, $agentId, $channel, $message, is_array($input['payload'] ?? null) ? $input['payload'] : []);
                return ['ok' => true, 'conversation' => $updated];

            case 'close':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                $agentId = isset($input['agent_id']) ? (int) $input['agent_id'] : null;
                if ($conversationId < 1) {
                    return $this->error('conversation_id required');
                }
                return ['ok' => true, 'conversation' => $this->engine()->closeConversation($tenantId, $conversationId, $agentId)];

            case 'webhook_whatsapp':
                $payload = is_array($input['payload'] ?? null) ? $input['payload'] : $input;
                $adapter = new WhatsAppChannelAdapter($this->engine(true));
                return ['ok' => true, 'conversation' => $adapter->ingest($tenantId, $payload)];

            case 'webhook_email':
                $payload = is_array($input['payload'] ?? null) ? $input['payload'] : $input;
                $adapter = new EmailChannelAdapter($this->engine(true));
                return ['ok' => true, 'conversation' => $adapter->ingest($tenantId, $payload)];

            case 'webhook_chat':
                $payload = is_array($input['payload'] ?? null) ? $input['payload'] : $input;
                $adapter = new WebChatChannelAdapter($this->engine(true));
                return ['ok' => true, 'conversation' => $adapter->ingest($tenantId, $payload)];

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
