<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Auth;
use Rateb\App\Core\TenantContext;
use Rateb\App\Core\SessionManager;

final class AiController extends Controller
{
    /**
     * Bind a real tenant for AI. Platform SA often has TenantContext=null while
     * leftover rateb_company_id still drives the branch bar — adopt that so chat works.
     */
    private function resolveAiCompanyId(): int
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);

        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = (int) rateb_resolve_ops_company_id();
        }

        if ($companyId < 1) {
            $sessionCompany = (int) (SessionManager::get('rateb_company_id', 0) ?? 0);
            if ($sessionCompany > 0) {
                if (function_exists('rateb_adopt_ops_company_id')) {
                    $companyId = (int) rateb_adopt_ops_company_id($sessionCompany);
                } else {
                    $companyId = $sessionCompany;
                }
            }
        }

        if ($companyId < 1 && function_exists('rateb_resolve_erp_shell_company_id')) {
            $isPlatformSa = (bool) SessionManager::get('rateb_is_super_admin')
                && function_exists('rateb_is_platform_oversight_host')
                && rateb_is_platform_oversight_host();
            // Platform SA without an explicit tenant stays at 0 (picker required).
            // Everyone else may fall back to shell/primary resolution.
            if (!$isPlatformSa) {
                $companyId = (int) rateb_resolve_erp_shell_company_id();
            }
        }

        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
            if (function_exists('rateb_sync_ops_session_to_company')) {
                rateb_sync_ops_session_to_company($companyId);
            }
            SessionManager::set('rateb_ops_company_explicit', 1);
        }

        return $companyId > 0 ? $companyId : 0;
    }

    /** Platform mode (no company): answer without tenant tools or a hard error. */
    private function platformModeReply(string $message): string
    {
        $q = trim($message);
        $locale = (string) SessionManager::get('rateb_locale', 'ar');
        $ar = $locale !== 'en';
        if ($q === '') {
            return $ar
                ? 'أنا RATEB AI. وضع المنصة بدون شركة جاهز للأسئلة العامة. لبيانات شركة (مشتريات، مخزون، حسابات) اختر الشركة من القائمة أعلاه.'
                : 'RATEB AI is ready in platform mode. Pick a company above for that company\'s purchases, inventory, and accounts.';
        }

        return $ar
            ? "وضع المنصة (بدون شركة) يعمل، لكن «{$q}» يحتاج دفتر شركة محددة.\nاختر الشركة من القائمة أعلاه — نفس الوكلاء الذين يعملون مع الشركات سيجيبون على بياناتها.\nفي وضع المنصة أقدر أشرح النظام والتنقل والصلاحيات بدون فتح بيانات شركة."
            : "Platform mode is on, but \"{$q}\" needs a specific company.\nChoose a company above and the same agents will answer from that company's data.\nIn platform mode I can explain the system, navigation, and permissions without opening a company ledger.";
    }

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

        $isSuperAdmin = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
        $isPlatformStaff = !$isSuperAdmin
            && (new \Rateb\App\Services\AuthorizationService())->userIsPlatformStaff((int) ($user['id'] ?? 0));
        $companyId = $this->resolveAiCompanyId();
        if (!$companyId && !$isSuperAdmin && !$isPlatformStaff) {
            $this->redirect(rateb_url('admin'));
            return;
        }

        $planLimits = new \Rateb\App\Services\PlanLimitService();
        if ($companyId && !$planLimits->companyHasModule($companyId, 'procurement')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => '403'], 'main');
            return;
        }

        // Never let SW / browser keep a stale AI document (buttons/scripts break when cached).
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $tower = null;
        $capabilities = [];
        $toolLabels = [];
        $ctx = \Rateb\App\Services\ProcurementAgentContext::fromSession();
        if ($ctx !== null && class_exists(\Rateb\App\Services\ErpControlTowerLayer::class)) {
            try {
                $tower = \Rateb\App\Services\ErpControlTowerLayer::snapshot($ctx, 12);
            } catch (\Throwable $e) {
                $tower = null;
            }
        }
        if ($ctx !== null && class_exists(\Rateb\App\Services\ErpDomainRegistry::class)) {
            try {
                $capabilities = \Rateb\App\Services\ErpDomainRegistry::userFacingCapabilities($ctx);
                $toolLabels = \Rateb\App\Services\ErpDomainRegistry::toolLabelsForUi($ctx);
            } catch (\Throwable $e) {
                $capabilities = [];
                $toolLabels = [];
            }
        }
        // Platform mode (no company): same chrome as tenant agents — empty tower + full capability chips.
        if (!is_array($tower) || $tower === []) {
            $tower = $this->emptyControlTowerShell((int) $companyId);
        }
        if ($capabilities === []) {
            $capabilities = $this->fallbackPlatformCapabilities();
        }

        $this->view('company/ai/index', [
            'title' => __('rateb_ai'),
            'locale' => SessionManager::get('rateb_locale', 'en'),
            'csrf' => \Rateb\App\Core\Csrf::token(),
            'chatEndpoint' => rateb_url(rateb_app_route('ai/chat')),
            'towerEndpoint' => rateb_url(rateb_app_route('ai/tower')),
            'aiCompanyId' => (int) $companyId,
            'aiUserId' => (int) ($user['id'] ?? 0),
            'aiNeedsCompany' => $companyId < 1,
            'controlTower' => is_array($tower) ? $tower : [],
            'aiCapabilities' => is_array($capabilities) ? $capabilities : [],
            'aiToolLabels' => is_array($toolLabels) ? $toolLabels : [],
        ], 'main');
    }

    /** @return array<string, mixed> */
    private function emptyControlTowerShell(int $companyId = 0): array
    {
        return [
            'company_id' => $companyId,
            'as_of' => date('Y-m-d H:i:s'),
            'overview' => [
                'kpi_count' => 0,
                'critical_risks' => 0,
                'open_warnings' => 0,
                'forecasts_available' => 0,
                'recommendations' => 0,
                'outcomes' => 0,
                'active_workflows' => 0,
            ],
            'kpis' => [],
            'risks' => [],
            'warnings' => [],
            'forecasts' => [],
            'recommendations' => [],
            'actions' => ['proposed' => [], 'executed' => [], 'verified' => [], 'failed' => []],
            'outcomes' => [],
            'learning' => [],
            'memory' => [],
            'workflows' => [],
            'agent_activity' => [],
            'agent_effectiveness' => [],
        ];
    }

    /** @return list<array{id:string,label:string,prompt:string,domain:string}> */
    private function fallbackPlatformCapabilities(): array
    {
        $defs = [
            ['id' => 'procurement', 'label' => 'ai_cap_procurement', 'prompt' => 'ai_suggest_list_pr', 'domain' => 'procurement'],
            ['id' => 'inventory', 'label' => 'ai_cap_inventory', 'prompt' => 'ai_suggest_inventory', 'domain' => 'inventory'],
            ['id' => 'suppliers', 'label' => 'ai_cap_suppliers', 'prompt' => 'ai_suggest_search_suppliers', 'domain' => 'suppliers'],
            ['id' => 'crm', 'label' => 'ai_cap_crm', 'prompt' => 'ai_suggest_crm', 'domain' => 'crm'],
            ['id' => 'accounting', 'label' => 'ai_cap_accounting', 'prompt' => 'ai_suggest_accounting', 'domain' => 'accounting'],
            ['id' => 'executive', 'label' => 'ai_cap_executive', 'prompt' => 'ai_suggest_executive', 'domain' => 'executive'],
            ['id' => 'warnings', 'label' => 'ai_cap_warnings', 'prompt' => 'ai_suggest_warnings', 'domain' => 'executive'],
            ['id' => 'learning', 'label' => 'ai_cap_learning', 'prompt' => 'ai_suggest_learning', 'domain' => 'executive'],
            ['id' => 'workflows', 'label' => 'ai_cap_workflows', 'prompt' => 'ai_suggest_workflow', 'domain' => 'executive'],
            ['id' => 'control_tower', 'label' => 'ai_cap_control_tower', 'prompt' => 'ai_suggest_control_tower', 'domain' => 'executive'],
            ['id' => 'memory', 'label' => 'ai_cap_memory', 'prompt' => 'ai_suggest_memory', 'domain' => 'executive'],
        ];
        $out = [];
        foreach ($defs as $d) {
            $labelKey = $d['label'];
            $promptKey = $d['prompt'];
            $out[] = [
                'id' => $d['id'],
                'label' => function_exists('__') ? __($labelKey) : $labelKey,
                'prompt' => function_exists('__') ? __($promptKey) : $promptKey,
                'domain' => $d['domain'],
            ];
        }

        return $out;
    }

    /**
     * Control Tower JSON snapshot (read-only). No ERP writes.
     */
    public function tower(): void
    {
        Auth::bootstrapFromSession();

        if (!Auth::user()) {
            $this->json(['success' => false, 'error' => 'unauthorized', 'message' => __('access_denied')], 401);
            return;
        }
        if (!rateb_can('ai.view')) {
            $this->json(['success' => false, 'error' => 'forbidden', 'message' => __('access_denied')], 403);
            return;
        }

        $companyId = $this->resolveAiCompanyId();
        if (!$companyId) {
            $this->json([
                'success' => true,
                'data' => $this->emptyControlTowerShell(0),
                'company_id' => 0,
                'platform_mode' => true,
            ]);
            return;
        }

        $planLimits = new \Rateb\App\Services\PlanLimitService();
        $hasAnyDomain = false;
        if (class_exists(\Rateb\App\Services\ErpDomainRegistry::class)) {
            foreach (\Rateb\App\Services\ErpDomainRegistry::getActiveDomains() as $d) {
                $mod = (string) ($d['module'] ?? '');
                if ($mod === 'dashboard' || ($mod !== '' && $planLimits->companyHasModule($companyId, $mod))) {
                    $hasAnyDomain = true;
                    break;
                }
            }
        }
        if (!$hasAnyDomain && !$planLimits->companyHasModule($companyId, 'procurement')) {
            $this->json(['success' => false, 'error' => 'forbidden', 'message' => __('access_denied')], 403);
            return;
        }

        $ctx = \Rateb\App\Services\ProcurementAgentContext::fromSession();
        if ($ctx === null) {
            $this->json(['success' => false, 'error' => 'unauthorized', 'message' => __('ai_auth_required')], 401);
            return;
        }
        if ((int) $ctx->companyId !== (int) $companyId) {
            $this->json(['success' => false, 'error' => 'tenant_mismatch', 'message' => __('access_denied')], 403);
            return;
        }

        try {
            \Rateb\App\Services\ErpControlTowerLayer::clearMemo();
            $snap = \Rateb\App\Services\ErpControlTowerLayer::snapshot($ctx, 12);
            $this->json([
                'success' => true,
                'data' => $snap,
                'company_id' => (int) $companyId,
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error' => 'tower_unavailable',
                'message' => __('ai_ct_service_unavailable'),
            ], 500);
        }
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

        $companyId = $this->resolveAiCompanyId();
        if (!$companyId) {
            if (!$this->validateCsrf()) {
                $this->json([
                    'success' => false,
                    'error' => 'csrf_invalid',
                    'message' => __('ai_csrf_invalid'),
                ], 403);
                return;
            }
            $rawPlatform = (string) file_get_contents('php://input');
            $bodyPlatform = json_decode($rawPlatform, true);
            if (!is_array($bodyPlatform)) {
                $bodyPlatform = [];
            }
            $platformMessage = trim((string) ($bodyPlatform['message'] ?? ''));
            $this->json([
                'success' => true,
                'request_id' => (string) ($bodyPlatform['request_id'] ?? ''),
                'data' => [
                    'response' => $this->platformModeReply($platformMessage),
                    'tool_calls' => [],
                    'pending_confirmations' => [],
                    'domain' => 'platform',
                    'agent' => 'rateb_platform_assistant',
                ],
            ]);
            return;
        }

        $planLimits = new \Rateb\App\Services\PlanLimitService();
        $hasAnyDomain = false;
        if (class_exists(\Rateb\App\Services\ErpDomainRegistry::class)) {
            foreach (\Rateb\App\Services\ErpDomainRegistry::getActiveDomains() as $d) {
                $mod = (string) ($d['module'] ?? '');
                if ($mod === 'dashboard' || ($mod !== '' && $planLimits->companyHasModule($companyId, $mod))) {
                    $hasAnyDomain = true;
                    break;
                }
            }
        }
        if (!$hasAnyDomain && !$planLimits->companyHasModule($companyId, 'procurement')) {
            $this->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => __('access_denied'),
            ], 403);
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

        // Unified RATEB ERP Agent Core
        if (!class_exists(\Rateb\App\Services\ErpAgent::class)
            || !class_exists(\Rateb\App\Services\ErpDomainRegistry::class)
            || !class_exists(\Rateb\App\Services\ProcurementAgent::class)
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

            $agent = new \Rateb\App\Services\ErpAgent(is_array($config) ? $config : []);
            $history = $body['history'] ?? [];
            if (!is_array($history)) {
                $history = [];
            }
            $history = $ctx->sanitizeHistory($history);
            $domain = strtolower(trim((string) ($body['domain'] ?? '')));
            // Empty/auto → intent routing (never hardcode procurement)
            $result = $agent->process([
                'message' => $message,
                'history' => $history,
                'request_id' => $requestId,
                'confirmed_writes' => $confirmedWrites,
                'conversation_scope' => $ctx->conversationScopeKey(),
                'domain' => ($domain !== '' && $domain !== 'auto' && $domain !== 'procurement_default')
                    ? $domain
                    : '',
            ], $ctx);

            $this->json([
                'success' => true,
                'request_id' => $requestId,
                'data' => [
                    'response' => (string) ($result['response'] ?? ''),
                    'tool_calls' => $result['tool_calls'] ?? [],
                    'pending_confirmations' => $result['pending_confirmations'] ?? [],
                    'domain' => (string) ($result['domain'] ?? ''),
                    'agent' => (string) ($result['agent'] ?? 'rateb_erp_agent'),
                    'execution_level' => (string) ($result['governance']['execution_level'] ?? ''),
                    'conversation_phase' => (string) ($result['observability']['conversation_phase'] ?? ''),
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
