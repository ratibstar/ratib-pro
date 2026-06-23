<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Portal\CustomerPortalAuthService;
use Ratib\ContactCenter\App\Application\Services\Portal\CustomerPortalService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Application\Services\SaaS\WhiteLabelService;
use Ratib\ContactCenter\App\Core\Security\PortalAuthContext;

final class CustomerPortalApiController
{
    public function __construct(
        private readonly CustomerPortalAuthService $auth = new CustomerPortalAuthService(),
        private readonly CustomerPortalService $portal = new CustomerPortalService(),
        private readonly WhiteLabelService $whiteLabel = new WhiteLabelService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code((int) (is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400));
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        if (in_array($action, ['login', 'branding_public'], true)) {
            return match ($action) {
                'login' => $this->handleLogin($input),
                'branding_public' => $this->handleBrandingPublic($input),
                default => ['ok' => false, 'error' => 'Unknown action'],
            };
        }
        $token = (string) ($input['portal_token'] ?? $_SERVER['HTTP_X_RCC_PORTAL_TOKEN'] ?? '');
        if ($token === '' || !$this->auth->authenticateToken($token)) {
            return ['ok' => false, 'error' => 'Portal authentication required'];
        }

        return match ($action) {
            'logout' => $this->handleLogout($token),
            'dashboard' => $this->ok($this->portal->dashboard()),
            'tickets' => $this->ok(['tickets' => $this->portal->tickets()]),
            'conversations' => $this->ok(['conversations' => $this->portal->conversations()]),
            'crm_profile' => $this->ok($this->portal->crmProfile()),
            'timeline' => $this->ok(['timeline' => $this->portal->timeline()]),
            'invoices' => $this->ok(['invoices' => $this->portal->invoices()]),
            'payments' => $this->ok(['payments' => $this->portal->payments()]),
            'recordings' => $this->ok(['recordings' => $this->portal->recordings()]),
            'knowledge' => $this->ok(['articles' => $this->portal->knowledgeBase((string) ($input['q'] ?? ''))]),
            'sla_dashboard' => $this->ok($this->portal->slaDashboard()),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @param array<string, mixed> $input */
    private function handleLogin(array $input): array
    {
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $tenantId = $this->whiteLabel->resolveTenantByDomain($host);
        }
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'tenant_id required'];
        }
        $result = $this->auth->login($tenantId, (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));
        return $result['ok'] ?? false
            ? ['ok' => true, 'portal_token' => $result['token'], 'tenant_id' => $result['tenant_id']]
            : ['ok' => false, 'error' => $result['error'] ?? 'Login failed'];
    }

  /** @param array<string, mixed> $input */
    private function handleBrandingPublic(array $input): array
    {
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            $tenantId = $this->whiteLabel->resolveTenantByDomain((string) ($_SERVER['HTTP_HOST'] ?? ''));
        }
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'tenant not resolved'];
        }
        return $this->ok(['branding' => $this->whiteLabel->branding($tenantId)]);
    }

    private function handleLogout(string $token): array
    {
        $this->auth->logout($token);
        PortalAuthContext::clear();
        return ['ok' => true];
    }

    /** @return array<string, mixed> */
    private function ok(mixed $data): array
    {
        return is_array($data) && isset($data['ok']) ? $data : ['ok' => true] + (is_array($data) ? $data : ['data' => $data]);
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
}
