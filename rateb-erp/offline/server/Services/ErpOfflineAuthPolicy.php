<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\Models\OfflineDevice;

/**
 * Phase 11 — ERP offline unlock policy (local shell unlock only).
 * Does not create PHP sessions or alter Auth / LoginController.
 */
final class ErpOfflineAuthPolicy
{
    public const LOGOUT_KEEP_VAULT = 'keep_vault';
    public const LOGOUT_CLEAR_VAULT = 'clear_vault';

    /** @return array{ok: bool, error?: string, company_id?: int, branch_id?: int, user_id?: int} */
    public function assertEnrollAllowed(): array
    {
        if (!(new OfflineFeatureFlagService())->isAuthUnlockEnabled()) {
            return ['ok' => false, 'error' => 'auth_unlock_disabled'];
        }

        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        $isSuper = !empty(SessionManager::get('rateb_is_super_admin'));
        $companyId = 0;
        // Prefer shell/ops resolver so company-bound super-admins (dedicated primary) can enroll.
        if (function_exists('rateb_resolve_erp_shell_company_id')) {
            $companyId = (int) rateb_resolve_erp_shell_company_id();
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_company_id', 0) ?? 0);
        }
        if ($companyId < 1) {
            $companyId = (int) (SessionManager::get('rateb_ops_company_id', 0) ?? 0);
        }

        // Platform super-admin with no tenant context cannot hold a warm company identity.
        if ($isSuper && $companyId < 1) {
            return ['ok' => false, 'error' => 'super_admin_denied'];
        }
        if ($userId < 1 || $companyId < 1) {
            return ['ok' => false, 'error' => 'online_session_required'];
        }

        $branchId = 0;
        if (function_exists('rateb_portal_branch_id')) {
            $branchId = (int) rateb_portal_branch_id();
        }
        if ($branchId < 1 && function_exists('rateb_active_branch_filter_id')) {
            $branchId = (int) rateb_active_branch_filter_id();
        }

        return [
            'ok' => true,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
        ];
    }

    /**
     * Fail-closed device gate for unlock (unknown / pending / revoked denied).
     *
     * @return array{ok: bool, error?: string, status?: string|null, device_id?: string}
     */
    public function assertDeviceActiveForUnlock(int $companyId, string $deviceId): array
    {
        $deviceId = trim($deviceId);
        if ($companyId < 1 || $deviceId === '') {
            return ['ok' => false, 'error' => 'device_unknown', 'device_id' => $deviceId, 'status' => null];
        }

        $guard = (new OfflineDeviceGuard())->assertActive($companyId, $deviceId);
        if (!($guard['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($guard['error'] ?? 'device_denied'),
                'device_id' => $deviceId,
                'status' => isset($guard['status']) ? (string) $guard['status'] : null,
            ];
        }

        return [
            'ok' => true,
            'device_id' => $deviceId,
            'status' => OfflineDevice::STATUS_ACTIVE,
        ];
    }

    public function logoutVaultPolicy(): string
    {
        $env = getenv('RATEB_OFFLINE_AUTH_LOGOUT_VAULT');
        if ($env === false || $env === '') {
            $env = (string) ($_ENV['RATEB_OFFLINE_AUTH_LOGOUT_VAULT'] ?? '');
        }
        $v = strtolower(trim((string) $env));
        // Phase P1 default: logout destroys warm identity + PIN vault.
        if ($v === self::LOGOUT_KEEP_VAULT || $v === 'keep') {
            return self::LOGOUT_KEEP_VAULT;
        }

        return self::LOGOUT_CLEAR_VAULT;
    }
}
