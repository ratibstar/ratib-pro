<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\MobileDeviceRegistryService;

/**
 * Thin shared mobile device registry adapter.
 * company_id / user_id from auth context only.
 */
final class MobileDeviceController extends Controller
{
    public function register(): void
    {
        try {
            $result = (new MobileDeviceRegistryService())->register(
                (int) TenantContext::apiUserId(),
                (int) TenantContext::companyId(),
                $this->jsonBody()
            );
            Response::json($result['body'], (int) $result['status']);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'code' => 'schema_outdated',
                'message' => class_exists(\Rateb\App\Services\DatabaseErrorService::class)
                    ? \Rateb\App\Services\DatabaseErrorService::userMessage($e)
                    : 'Device registry unavailable',
            ], 503);
        }
    }

    public function heartbeat(): void
    {
        $result = (new MobileDeviceRegistryService())->heartbeat(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->jsonBody()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function revoke(array $params = []): void
    {
        $result = (new MobileDeviceRegistryService())->revoke(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function pushToken(): void
    {
        $result = (new MobileDeviceRegistryService())->updatePushToken(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $this->jsonBody()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return is_array($_POST) ? $_POST : [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
