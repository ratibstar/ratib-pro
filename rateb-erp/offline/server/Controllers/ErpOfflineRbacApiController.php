<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\ErpOfflineRbacManifestService;
use Rateb\App\Offline\Services\ErpOfflineRbacPolicy;
use Rateb\App\Offline\Services\ErpOfflineRbacVersionService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

/**
 * Phase 12 — ERP offline RBAC manifest API (additive, UI cache only).
 * Does not create sessions or alter Auth / middleware.
 */
final class ErpOfflineRbacApiController extends Controller
{
    public function version(): void
    {
        $this->gate();
        $policy = (new ErpOfflineRbacPolicy())->assertManifestAllowed();
        if (!($policy['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => ['code' => (string) ($policy['error'] ?? 'denied')]], 403);
            return;
        }
        $version = (new ErpOfflineRbacVersionService())->currentVersion(
            (int) $policy['company_id'],
            (int) $policy['user_id'],
            (int) ($policy['branch_id'] ?? 0)
        );
        $this->json([
            'ok' => true,
            'rbac_version' => $version,
            'company_id' => (int) $policy['company_id'],
            'branch_id' => (int) ($policy['branch_id'] ?? 0),
            'user_id' => (int) $policy['user_id'],
            'ttl_seconds' => (new ErpOfflineRbacPolicy())->ttlSeconds(),
        ]);
    }

    public function manifest(): void
    {
        $this->gate();
        $built = (new ErpOfflineRbacManifestService())->buildForSession();
        if (!($built['ok'] ?? false)) {
            $this->json(['ok' => false, 'error' => ['code' => (string) ($built['error'] ?? 'denied')]], 403);
            return;
        }
        $this->json([
            'ok' => true,
            'manifest' => $built['manifest'],
            'ui_only' => true,
            'server_authz_bypass' => false,
        ]);
    }

    private function gate(): void
    {
        if (!(new OfflineFeatureFlagService())->isRbacCacheEnabled()) {
            Response::json([
                'ok' => false,
                'error' => ['message' => 'rbac_cache_disabled', 'code' => 'rbac_cache_disabled'],
            ], 403);
            exit;
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
    }
}
