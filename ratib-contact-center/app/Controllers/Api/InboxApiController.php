<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
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
            $this->engine = new ConversationEngine(eventBus: EventBus::instance());
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
        if ($action === 'health') {
            return [
                'ok' => true,
                'service' => 'rcc-inbox',
                'php' => PHP_VERSION,
            ];
        }

        if ($action === 'chat_widget_config') {
            $tenantId = (int) ($input['tenant_id'] ?? 0);
            if ($tenantId < 1) {
                return $this->error('tenant_id required');
            }
            $cfg = (array) require dirname(__DIR__, 3) . '/config/omnichannel.php';
            return [
                'ok' => true,
                'widget' => [
                    'enabled' => (bool) ($cfg['web_chat']['widget_enabled'] ?? true),
                    'api_url' => '/ratib-contact-center/public/api/v1/inbox.php',
                    'tenant_id' => $tenantId,
                    'script' => '/ratib-contact-center/public/assets/js/rcc-webchat-widget.js',
                ],
            ];
        }

        if (in_array($action, ['webhook_whatsapp', 'webhook_email', 'webhook_chat'], true)) {
            $tenantId = TenantContext::tenantId() ?? (int) ($input['tenant_id'] ?? 0);
            if ($tenantId < 1) {
                return $this->error('tenant_id required');
            }
            TenantContext::set($tenantId);
            $payload = is_array($input['payload'] ?? null) ? $input['payload'] : $input;
            $adapter = match ($action) {
                'webhook_whatsapp' => new WhatsAppChannelAdapter($this->engine(true)),
                'webhook_email' => new EmailChannelAdapter($this->engine(true)),
                default => new WebChatChannelAdapter($this->engine(true)),
            };
            return ['ok' => true, 'conversation' => $adapter->ingest($tenantId, $payload)];
        }

        AuthContext::requirePermission('rcc.inbox.manage');
        $tenantId = AuthContext::tenantId();
        $agentId = AuthContext::agentId();
        TenantContext::set($tenantId);

        switch ($action) {
            case 'inbox':
                return ['ok' => true, 'conversations' => $this->engine()->inboxForAgent($tenantId, $agentId)];

            case 'thread':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                if ($conversationId < 1) {
                    return $this->error('conversation_id required');
                }
                return ['ok' => true] + $this->engine()->thread($tenantId, $conversationId);

            case 'send':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                $channel = (string) ($input['channel'] ?? 'chat');
                $message = (string) ($input['message'] ?? '');
                if ($conversationId < 1 || $message === '') {
                    return $this->error('conversation_id and message required');
                }
                $updated = $this->engine(true)->sendOutbound($tenantId, $conversationId, $agentId, $channel, $message, is_array($input['payload'] ?? null) ? $input['payload'] : []);
                $this->dispatchOutbound($tenantId, $conversationId, $channel, $message, $updated);
                return ['ok' => true, 'conversation' => $updated];

            case 'close':
                $conversationId = (int) ($input['conversation_id'] ?? 0);
                if ($conversationId < 1) {
                    return $this->error('conversation_id required');
                }
                return ['ok' => true, 'conversation' => $this->engine()->closeConversation($tenantId, $conversationId, $agentId)];

            case 'start_demo':
                AuthContext::requirePermission('rcc.admin.settings');
                $adapter = new WebChatChannelAdapter($this->engine(true));
                $conversation = $adapter->ingest($tenantId, [
                    'message' => (string) ($input['message'] ?? 'Hello, I need help with my order.'),
                    'email' => 'demo.customer@rateb.sa',
                    'name' => 'Demo Customer',
                    'session_id' => 'demo-' . gmdate('YmdHis'),
                ]);
                return ['ok' => true, 'conversation' => $conversation];

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

    /** @param array<string, mixed> $conversation */
    private function dispatchOutbound(int $tenantId, int $conversationId, string $channel, string $message, array $conversation): void
    {
        $dispatcher = new \Ratib\ContactCenter\App\Infrastructure\Omnichannel\OutboundDispatcher();
        $dispatcher->dispatch($tenantId, $conversationId, $channel, $message, $conversation);
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
