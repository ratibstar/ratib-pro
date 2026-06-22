<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\Softphone\CallControlEngine;

/**
 * Thin API — delegates ALL logic to CallControlEngine (no SIP in controller).
 */
final class SoftphoneApiController
{
    private CallControlEngine $engine;

    public function __construct(?CallControlEngine $engine = null)
    {
        $this->engine = $engine ?? new CallControlEngine(eventBus: EventBus::instance());
    }

    private function bootRealtimeIfNeeded(): void
    {
        RealtimeOrchestrator::boot();
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = (string) ($_GET['action'] ?? '');
        $body = $this->parseJsonBody();

        try {
            AuthContext::requirePermission('rcc.calls.manage');
            $tenantId = AuthContext::tenantId();
            $agentId = AuthContext::agentId();
            TenantContext::set($tenantId);

            switch ($action) {
                case 'register':
                    $this->bootRealtimeIfNeeded();
                    $result = $this->engine->registerAgentSession(
                        $tenantId,
                        $agentId,
                        isset($body['user_id']) ? (int) $body['user_id'] : null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    );
                    break;
                case 'ping':
                    $this->engine->pingSession($tenantId, $agentId);
                    $result = ['ok' => true];
                    break;
                case 'unregister':
                    $this->engine->unregisterAgentSession($tenantId, $agentId);
                    $result = ['ok' => true];
                    break;
                case 'outbound':
                    $result = $this->engine->initiateOutbound($tenantId, $agentId, (string) ($body['destination'] ?? ''));
                    break;
                case 'accept':
                    $result = $this->engine->acceptInbound(
                        $tenantId,
                        $agentId,
                        (int) ($body['call_id'] ?? 0),
                        (string) ($body['remote_number'] ?? ''),
                        isset($body['queue_id']) ? (int) $body['queue_id'] : null,
                        isset($body['sip_call_id']) ? (string) $body['sip_call_id'] : null,
                        isset($body['channel_id']) ? (string) $body['channel_id'] : null
                    );
                    break;
                case 'connected':
                    $result = $this->engine->markConnected(
                        $tenantId,
                        $agentId,
                        (int) ($body['softphone_call_id'] ?? 0),
                        isset($body['call_id']) ? (int) $body['call_id'] : null,
                        isset($body['queue_id']) ? (int) $body['queue_id'] : null,
                        isset($body['remote_number']) ? (string) $body['remote_number'] : null
                    );
                    break;
                case 'hold':
                    $result = $this->engine->holdCall($tenantId, $agentId, (int) ($body['softphone_call_id'] ?? 0));
                    break;
                case 'resume':
                    $result = $this->engine->resumeCall($tenantId, $agentId, (int) ($body['softphone_call_id'] ?? 0));
                    break;
                case 'hangup':
                    $result = $this->engine->hangup($tenantId, $agentId, (int) ($body['softphone_call_id'] ?? 0));
                    break;
                case 'transfer_blind':
                    $result = $this->engine->blindTransfer(
                        $tenantId,
                        $agentId,
                        (int) ($body['softphone_call_id'] ?? 0),
                        (string) ($body['target_extension'] ?? ''),
                        isset($body['channel_id']) ? (string) $body['channel_id'] : null
                    );
                    break;
                case 'transfer_attended':
                    $result = $this->engine->attendedTransfer(
                        $tenantId,
                        $agentId,
                        (int) ($body['softphone_call_id'] ?? 0),
                        (string) ($body['target_extension'] ?? ''),
                        !empty($body['complete']),
                        isset($body['channel_id']) ? (string) $body['channel_id'] : null
                    );
                    break;
                case 'active':
                    $result = $this->engine->activeCall($tenantId, $agentId) ?? [];
                    break;
                case 'settings':
                    $result = ['auto_answer_queue_calls' => $this->engine->shouldAutoAnswer($tenantId)];
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown action: ' . $action);
            }

            echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        if ($this->parseJsonBodyCache !== null) {
            return $this->parseJsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $this->parseJsonBodyCache = [];
        }
        $decoded = json_decode($raw, true);
        return $this->parseJsonBodyCache = is_array($decoded) ? $decoded : [];
    }

    /** @var array<string, mixed>|null */
    private ?array $parseJsonBodyCache = null;
}
