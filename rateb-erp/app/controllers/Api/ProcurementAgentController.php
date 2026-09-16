<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuthorizationService;
use Rateb\App\Services\PlanLimitService;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;

/**
 * Procurement Agent API Controller
 * POST /api/v1/agent/procurement
 *
 * Security parity with Admin AiController::chat:
 * - ai.view (session rateb_can / Bearer RBAC + implies)
 * - procurement plan module
 * - confirmed_writes + PolicyGuard inside ProcurementAgent
 *
 * Bearer path must NOT call Auth::bootstrapFromSession() (it would wipe ApiAuthMiddleware tenant).
 */
final class ProcurementAgentController extends Controller
{
    public function process(array $params = []): void
    {
        $requestId = '';
        try {
            $apiToken = $this->getApiToken();
            $apiUserId = TenantContext::apiUserId();
            $apiCompanyId = TenantContext::companyId();

            if (!$apiToken) {
                Auth::bootstrapFromSession();
            }

            $sessionUser = Auth::user();
            $userId = $sessionUser ? (int) ($sessionUser['id'] ?? 0) : (int) ($apiUserId ?? 0);

            if ($userId < 1) {
                Response::json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => __('ai_auth_required'),
                ], 401);
                return;
            }

            if (!$this->userHasAiView($userId)) {
                Response::json([
                    'success' => false,
                    'error' => 'forbidden',
                    'message' => __('access_denied'),
                ], 403);
                return;
            }

            if ($apiToken) {
                // Restore/keep middleware tenant — never trust a wiped session context.
                if ($apiCompanyId) {
                    TenantContext::setCompanyId((int) $apiCompanyId);
                }
                if ($apiUserId) {
                    TenantContext::setApiUserId((int) $apiUserId);
                }
            }

            $companyId = (int) (TenantContext::companyId() ?: 0);
            if ($companyId < 1) {
                Response::json([
                    'success' => false,
                    'error' => 'company_required',
                    'message' => __('ai_company_required'),
                ], 400);
                return;
            }

            $planLimits = new PlanLimitService();
            if (!$planLimits->companyHasModule($companyId, 'procurement')) {
                Response::json([
                    'success' => false,
                    'error' => 'forbidden',
                    'message' => __('access_denied'),
                ], 403);
                return;
            }

            if (!$apiToken && !$this->validateCsrf()) {
                Response::json([
                    'success' => false,
                    'error' => 'csrf_invalid',
                    'message' => __('ai_csrf_invalid'),
                ], 403);
                return;
            }

            $body = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $body = [];
            }

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

            $confirmedWrites = $body['confirmed_writes'] ?? [];
            if (!is_array($confirmedWrites)) {
                $confirmedWrites = [];
            }

            $requestId = (string) ($body['request_id'] ?? bin2hex(random_bytes(16)));

            $ctx = null;
            if ($apiToken && $apiUserId) {
                $ctx = ProcurementAgentContext::fromApiUser($userId, $companyId);
            } else {
                $ctx = ProcurementAgentContext::fromSession();
            }
            if (!$ctx) {
                Response::json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => __('ai_auth_required'),
                    'request_id' => $requestId,
                ], 401);
                return;
            }

            $config = require RATEB_ROOT . '/config/agent.php';
            $agent = new ProcurementAgent(is_array($config) ? $config : []);
            $result = $agent->process([
                'message' => $message,
                'history' => $history,
                'request_id' => $requestId,
                'confirmed_writes' => $confirmedWrites,
            ], $ctx);

            Response::json([
                'success' => true,
                'request_id' => $requestId,
                'data' => [
                    'response' => (string) ($result['response'] ?? ''),
                    'tool_calls' => $result['tool_calls'] ?? [],
                    'pending_confirmations' => $result['pending_confirmations'] ?? [],
                    'audit' => $result['audit'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            // Never leak secrets/stack; always JSON for this API.
            Response::json([
                'success' => false,
                'error' => 'agent_error',
                'message' => __('ai_agent_failed'),
                'request_id' => $requestId !== '' ? $requestId : null,
            ], 500);
        }
    }

    private function userHasAiView(int $userId): bool
    {
        if (rateb_can('ai.view')) {
            return true;
        }
        if (TenantContext::isSuperAdmin()) {
            return true;
        }
        $slugs = (new AuthorizationService())->userPermissionSlugs($userId);
        if (in_array('ai.view', $slugs, true)) {
            return true;
        }
        /* Do not treat procurement.manage as ai.view — matrix toggle must be authoritative. */
        $cfgFile = RATEB_ROOT . '/config/permissions-system.php';
        $cfg = is_file($cfgFile) ? require $cfgFile : [];
        $implies = is_array($cfg['permission_implies'] ?? null) ? $cfg['permission_implies'] : [];
        foreach ($implies as $parent => $children) {
            if (!in_array($parent, $slugs, true)) {
                continue;
            }
            foreach ((array) $children as $child) {
                if ((string) $child === 'ai.view') {
                    return true;
                }
            }
        }
        return false;
    }

    private function getApiToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return null;
    }

    protected function validateCsrf(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!$token) {
            return false;
        }
        return Csrf::validate($token);
    }
}
