<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\ErpOfflineAuthDeviceService;
use Rateb\App\Offline\Services\ErpOfflineAuthPolicy;
use Rateb\App\Offline\Services\ErpOfflineIdentityEnrollService;
use Rateb\App\Offline\Services\ErpOfflineIdentityService;
use Rateb\App\Offline\Services\ErpOfflineIdentitySessionPolicy;
use Rateb\App\Offline\Services\OfflineBootstrapManager;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

/**
 * Phase 11 — ERP offline auth device enroll API (additive).
 * Requires live session; does not create sessions or alter Auth.
 */
final class ErpOfflineAuthApiController extends Controller
{
    public function deviceRegister(): void
    {
        $this->gate();
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => ['code' => (string) ($policy['error'] ?? 'denied')]], 403);
            return;
        }
        $result = (new ErpOfflineAuthDeviceService())->register(
            (int) $policy['company_id'],
            (int) $policy['user_id'],
            (int) ($policy['branch_id'] ?? 0),
            $body
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function deviceHeartbeat(): void
    {
        $this->gate();
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => ['code' => (string) ($policy['error'] ?? 'denied')]], 403);
            return;
        }
        $result = (new ErpOfflineAuthDeviceService())->heartbeat(
            (int) $policy['company_id'],
            (int) $policy['user_id'],
            $body
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    public function policy(): void
    {
        $this->gate();
        $policy = new ErpOfflineAuthPolicy();
        $enroll = $policy->assertEnrollAllowed();
        $identity = new ErpOfflineIdentityService();
        $flags = new OfflineFeatureFlagService();
        $coldOn = $flags->isColdIdentityEnabled();
        $payload = [
            'ok' => true,
            'auth_unlock' => $flags->isAuthUnlockEnabled(),
            'enroll' => $enroll,
            'logout_vault_policy' => $policy->logoutVaultPolicy(),
            'identity_ttl_seconds' => $identity->ttlSeconds(),
            'session_policy' => (new ErpOfflineIdentitySessionPolicy())->snapshot(),
            'warm_identity' => true,
            'cold_identity' => $coldOn,
            'is_super_admin' => !empty(SessionManager::get('rateb_is_super_admin')),
        ];
        if ($coldOn) {
            $payload['cold_boot'] = (new OfflineBootstrapManager())->coldBootConfig();
        }
        $this->json($payload);
    }

    /** Phase P1 — issue signed warm identity + activate ERP shell device (online session only). */
    public function identityEnroll(): void
    {
        $this->gate();
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $policy = (new ErpOfflineAuthPolicy())->assertEnrollAllowed();
        if (!($policy['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => ['code' => (string) ($policy['error'] ?? 'denied')]], 403);
            return;
        }
        $result = (new ErpOfflineIdentityEnrollService())->enroll(
            (int) $policy['company_id'],
            (int) $policy['user_id'],
            (int) ($policy['branch_id'] ?? 0),
            $body
        );
        $this->json($result, !empty($result['ok']) ? 200 : 403);
    }

    private function gate(): void
    {
        if (!(new OfflineFeatureFlagService())->isAuthUnlockEnabled()) {
            Response::json([
                'ok' => false,
                'error' => ['message' => 'auth_unlock_disabled', 'code' => 'auth_unlock_disabled'],
            ], 403);
            exit;
        }
        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_erp_shell_company_id')) {
            $companyId = (int) rateb_resolve_erp_shell_company_id();
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_company_id', 0) ?? 0);
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        }
        if ($companyId < 1 || $userId < 1) {
            Response::json([
                'ok' => false,
                'error' => ['message' => 'Unauthorized', 'code' => 'unauthorized'],
            ], 401);
            exit;
        }
        TenantContext::setCompanyId($companyId);
        if (function_exists('rateb_sync_ops_session_to_company')) {
            rateb_sync_ops_session_to_company($companyId);
        }
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
