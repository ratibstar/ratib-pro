<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Marketplace\MarketplaceService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class MarketplaceApiController
{
    public function __construct(private readonly MarketplaceService $marketplace = new MarketplaceService())
    {
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
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.marketplace.view');
        $tenantId = AuthContext::can('rcc.tenants.manage') && isset($input['tenant_id']) ? (int) $input['tenant_id'] : AuthContext::tenantId();
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'catalog' => $this->ok(['addons' => $this->marketplace->catalog($input['category'] ?? null)]),
            'subscribed' => $this->ok(['addons' => $this->marketplace->tenantAddons($tenantId)]),
            'subscribe' => $this->runPerm('rcc.marketplace.subscribe', fn () => $this->ok([
                'addons' => $this->marketplace->subscribe($tenantId, (int) ($input['addon_id'] ?? 0), $userId, is_array($input['config'] ?? null) ? $input['config'] : null),
            ])),
            'unsubscribe' => $this->runPerm('rcc.marketplace.manage', function () use ($tenantId, $input, $userId) {
                $this->marketplace->unsubscribe($tenantId, (int) ($input['addon_id'] ?? 0), $userId);
                return $this->ok(['unsubscribed' => true]);
            }),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @return array<string, mixed> */
    private function ok(mixed $data): array
    {
        return is_array($data) && isset($data['ok']) ? $data : ['ok' => true] + (is_array($data) ? $data : ['data' => $data]);
    }

    private function runPerm(string $perm, callable $fn): array
    {
        AuthContext::requirePermission($perm);
        return $fn();
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
