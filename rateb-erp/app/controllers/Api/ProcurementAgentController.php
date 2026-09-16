<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Auth;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\AuditService;

/**
 * Procurement Agent API Controller
 * POST /api/v1/agent/procurement
 */
final class ProcurementAgentController extends Controller
{
    private ProcurementAgent $agent;

    public function __construct()
    {
        $config = require RATEB_ROOT . '/config/agent.php';
        $this->agent = new ProcurementAgent($config);
    }

    public function process(array $params): void
    {
        // Auth bootstrap must run first
        Auth::bootstrapFromSession();

        // Create trusted context from session
        $ctx = ProcurementAgentContext::fromSession();
        if (!$ctx) {
            Response::json([
                'success' => false,
                'error' => 'unauthorized',
                'message' => __('ai_auth_required'),
            ], 401);
            return;
        }

        // Check CSRF for non-API token requests
        $apiToken = $this->getApiToken();
        if (!$apiToken && !$this->validateCsrf()) {
            Response::json([
                'success' => false,
                'error' => 'csrf_invalid',
                'message' => __('ai_csrf_invalid'),
            ], 403);
            return;
        }

        // Parse request body
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            Response::json([
                'success' => false,
                'error' => 'invalid_request',
                'message' => __('ai_message_required'),
            ], 400);
            return;
        }

        $history = $body['history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $requestId = $body['request_id'] ?? bin2hex(random_bytes(16));

        // Process through agent
        $result = $this->agent->process([
            'message' => $message,
            'history' => $history,
            'request_id' => $requestId,
        ], $ctx);

        Response::json([
            'success' => true,
            'data' => [
                'response' => $result['response'],
                'tool_calls' => $result['tool_calls'],
                'audit' => $result['audit'],
            ],
            'request_id' => $result['audit'][0]['request_id'] ?? bin2hex(random_bytes(16)),
        ]);
    }

    private function getApiToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return null;
    }

    private function validateCsrf(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!$token) {
            return false;
        }
        return Csrf::validate($token);
    }
}