<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\DeviceTrustService;
use Rateb\App\Offline\Services\ErpOfflineAuthPolicy;
use Rateb\App\Offline\Services\ErpOfflineIdentityRenewService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

/**
 * Phase P2 — offline device trust admin API (additive).
 */
final class ErpOfflineDeviceTrustApiController extends Controller
{
    public function devices(): void
    {
        $ctx = $this->gate('view');
        if ($ctx === null) {
            return;
        }
        $filters = [
            'branch_id' => isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : null,
            'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'status' => isset($_GET['status']) ? (string) $_GET['status'] : null,
        ];
        $list = (new DeviceTrustService())->listDevices((int) $ctx['company_id'], $filters);
        $this->json(['ok' => true, 'devices' => $list]);
    }

    public function rename(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $result = (new DeviceTrustService())->rename(
            (int) $ctx['company_id'],
            (string) ($body['device_id'] ?? ''),
            (string) ($body['nickname'] ?? ''),
            (int) $ctx['user_id']
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function revoke(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $result = (new DeviceTrustService())->revoke(
            (int) $ctx['company_id'],
            (string) ($body['device_id'] ?? ''),
            (int) $ctx['user_id'],
            isset($body['reason']) ? (string) $body['reason'] : null
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function renew(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $result = (new ErpOfflineIdentityRenewService())->renew(
            (int) $ctx['company_id'],
            (int) $ctx['user_id'],
            (int) ($ctx['branch_id'] ?? 0),
            (string) ($body['device_id'] ?? '')
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function logoutDevice(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $result = (new DeviceTrustService())->forceLogout(
            (int) $ctx['company_id'],
            (string) ($body['device_id'] ?? ''),
            (int) $ctx['user_id']
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function revokeAll(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $branchId = isset($body['branch_id']) ? (int) $body['branch_id'] : null;
        $userId = isset($body['user_id']) ? (int) $body['user_id'] : null;
        $result = (new DeviceTrustService())->revokeAll(
            (int) $ctx['company_id'],
            (int) $ctx['user_id'],
            $branchId,
            $userId
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function restore(): void
    {
        $ctx = $this->gate('manage');
        if ($ctx === null) {
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $result = (new DeviceTrustService())->restore(
            (int) $ctx['company_id'],
            (string) ($body['device_id'] ?? '')
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    /**
     * @return array{company_id: int, user_id: int, branch_id: int}|null
     */
    private function gate(string $mode): ?array
    {
        if (!(new OfflineFeatureFlagService())->isAuthUnlockEnabled()
            && !$this->hasDevicePermission($mode)) {
            Response::json([
                'ok' => false,
                'error' => ['message' => 'auth_unlock_disabled', 'code' => 'auth_unlock_disabled'],
            ], 403);
            exit;
        }

        if (!$this->hasDevicePermission($mode)) {
            // Fallback: company session with auth unlock enabled (company admin path).
            $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
            if (!($policy['ok'] ?? false) || !$this->isCompanyAdminSession()) {
                Response::json([
                    'ok' => false,
                    'error' => ['message' => 'Forbidden', 'code' => 'forbidden'],
                ], 403);
                exit;
            }
            return [
                'company_id' => (int) $policy['company_id'],
                'user_id' => (int) $policy['user_id'],
                'branch_id' => (int) ($policy['branch_id'] ?? 0),
            ];
        }

        $companyId = (int) (TenantContext::companyId() ?? SessionManager::get('rateb_company_id', 0) ?? 0);
        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        if ($companyId < 1 || $userId < 1) {
            Response::json([
                'ok' => false,
                'error' => ['message' => 'Unauthorized', 'code' => 'unauthorized'],
            ], 401);
            exit;
        }
        $branchId = 0;
        if (function_exists('rateb_portal_branch_id')) {
            $branchId = (int) rateb_portal_branch_id();
        }

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'branch_id' => $branchId,
        ];
    }

    private function hasDevicePermission(string $mode): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (!function_exists('rateb_can')) {
            return false;
        }
        try {
            if ($mode === 'manage') {
                return rateb_can('offline.devices.manage');
            }

            return rateb_can('offline.devices.view') || rateb_can('offline.devices.manage');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isCompanyAdminSession(): bool
    {
        if (!empty(SessionManager::get('rateb_is_super_admin'))) {
            return false;
        }
        if (function_exists('rateb_can')) {
            try {
                if (rateb_can('offline.devices.manage') || rateb_can('access.manage')) {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }
        $role = strtolower((string) (SessionManager::get('rateb_role_slug', '') ?? ''));

        return in_array($role, ['company_admin', 'admin', 'owner'], true);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requireCsrfOrAbort(): void
    {
        $token = '';
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (isset($_POST['_csrf'])) {
            $token = (string) $_POST['_csrf'];
        } else {
            $body = $this->jsonBody();
            $token = (string) ($body['_csrf'] ?? $body['csrf'] ?? '');
        }
        if ($token !== '' && Csrf::validate($token)) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'CSRF', 'code' => 'csrf'],
        ], 419);
        exit;
    }
}
