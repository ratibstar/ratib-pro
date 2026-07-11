<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Offline\OfflineModule;

/**
 * Phase 12 — Offline RBAC policy (UI cache only).
 * Does not create PHP sessions or alter Auth / LoginController / ErpAuthMiddleware.
 */
final class ErpOfflineRbacPolicy
{
    public const DEFAULT_TTL_SECONDS = 43200; // 12h

    /** @return array{ok: bool, error?: string, company_id?: int, branch_id?: int, user_id?: int} */
    public function assertManifestAllowed(): array
    {
        if (!(new OfflineFeatureFlagService())->isRbacCacheEnabled()) {
            return ['ok' => false, 'error' => 'rbac_cache_disabled'];
        }

        if (!empty(SessionManager::get('rateb_is_super_admin'))) {
            return ['ok' => false, 'error' => 'super_admin_denied'];
        }

        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        $companyId = (int) (SessionManager::get('rateb_company_id', 0) ?? 0);
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

    public function ttlSeconds(): int
    {
        $env = getenv('RATEB_OFFLINE_RBAC_TTL_SECONDS');
        if ($env === false || $env === '') {
            $env = (string) ($_ENV['RATEB_OFFLINE_RBAC_TTL_SECONDS'] ?? '');
        }
        $n = (int) $env;
        if ($n >= 60 && $n <= 604800) {
            return $n;
        }

        return self::DEFAULT_TTL_SECONDS;
    }

    /** @return list<string> */
    public function offlineDisabledModules(): array
    {
        $file = OfflineModule::rootPath() . '/config/offline-nav-catalog.php';
        $cfg = is_file($file) ? require $file : [];
        $mods = is_array($cfg['offline_disabled_modules'] ?? null) ? $cfg['offline_disabled_modules'] : [];

        return array_values(array_map('strval', $mods));
    }
}
