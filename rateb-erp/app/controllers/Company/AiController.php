<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Auth;
use Rateb\App\Core\TenantContext;
use Rateb\App\Core\SessionManager;

final class AiController extends Controller
{
    public function index(): void
    {
        Auth::bootstrapFromSession();

        $user = Auth::user();
        if (!$user) {
            $this->redirect(rateb_url('login'));
            return;
        }

        if (!rateb_can('ai.view')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => '403'], 'main');
            return;
        }

        $companyId = TenantContext::companyId();
        if (!$companyId) {
            $this->redirect(rateb_url('admin'));
            return;
        }

        $planLimits = new \Rateb\App\Services\PlanLimitService();
        if (!$planLimits->companyHasModule($companyId, 'procurement')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => '403'], 'main');
            return;
        }

        // Never let SW / browser keep a stale AI document (buttons/scripts break when cached).
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->view('company/ai/index', [
            'title' => __('rateb_ai'),
            'locale' => SessionManager::get('rateb_locale', 'en'),
            'csrf' => \Rateb\App\Core\Csrf::token(),
            'chatEndpoint' => rateb_url(rateb_app_route('ai/chat')),
            'aiCompanyId' => (int) $companyId,
            'aiUserId' => (int) ($user['id'] ?? 0),
        ], 'main');
    }

    public function chat(): void
    {
        Auth::bootstrapFromSession();

        if (!Auth::user()) {
            $this->json([
                'success' => false,
                'error' => 'unauthorized',
                'message' => __('access_denied'),
            ], 401);
            return;
        }

        if (!rateb_can('ai.view')) {
            $this->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => __('access_denied'),
            ], 403);
            return;
        }

        $companyId = TenantContext::companyId();
        if (!$companyId) {
            $this->json([
                'success' => false,
                'error' => 'company_required',
                'message' => __('company_required') !== 'company_required'
                    ? __('company_required')
                    : __('ai_company_required'),
            ], 400);
            return;
        }

        if (!$this->validateCsrf()) {
            $this->json([
                'success' => false,
                'error' => 'csrf_invalid',
                'message' => __('ai_csrf_invalid'),
            ], 403);
            return;
        }

        $planLimits = new \Rateb\App\Services\PlanLimitService();
        if (!$planLimits->companyHasModule($companyId, 'procurement')) {
            $this->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => __('access_denied'),
            ], 403);
            return;
        }

        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            $this->json([
                'success' => false,
                'error' => 'invalid_request',
                'message' => __('ai_message_required'),
            ], 400);
            return;
        }

        $requestId = (string) ($body['request_id'] ?? bin2hex(random_bytes(8)));
        $confirmedWrites = $body['confirmed_writes'] ?? [];
        if (!is_array($confirmedWrites)) {
            $confirmedWrites = [];
        }

        // Single MVP runtime path: ProcurementAgent via this endpoint.
        if (!class_exists(\Rateb\App\Services\ProcurementAgent::class)
            || !class_exists(\Rateb\App\Services\ProcurementAgentContext::class)
            || !is_file(RATEB_ROOT . '/config/agent.php')
        ) {
            $this->json([
                'success' => false,
                'error' => 'agent_unavailable',
                'message' => __('ai_agent_unavailable'),
                'request_id' => $requestId,
            ], 503);
            return;
        }

        try {
            $config = require RATEB_ROOT . '/config/agent.php';
            $ctx = \Rateb\App\Services\ProcurementAgentContext::fromSession();
            if (!$ctx) {
                $this->json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => __('ai_auth_required'),
                    'request_id' => $requestId,
                ], 401);
                return;
            }

            $agent = new \Rateb\App\Services\ProcurementAgent(is_array($config) ? $config : []);
            $history = $body['history'] ?? [];
            if (!is_array($history)) {
                $history = [];
            }
            $result = $agent->process([
                'message' => $message,
                'history' => $history,
                'request_id' => $requestId,
                'confirmed_writes' => $confirmedWrites,
            ], $ctx);

            $this->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => [
                    'response' => (string) ($result['response'] ?? ''),
                    'tool_calls' => $result['tool_calls'] ?? [],
                    'pending_confirmations' => $result['pending_confirmations'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => 'agent_error',
                'message' => __('ai_agent_failed'),
                'request_id' => $requestId,
            ], 500);
        }
    }
}
