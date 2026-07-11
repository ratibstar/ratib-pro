<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineMonitoringService;

/**
 * Read-only Offline Operations Dashboard (Phase 6).
 * Company shell — no writes / no process / no resolve actions.
 */
final class OfflineOpsDashboardController extends Controller
{
    public function index(): void
    {
        $this->requireCompany();
        if (!(new OfflineFeatureFlagService())->enabled('offline.monitoring')) {
            $this->view('offline/ops/disabled', [
                'title' => 'Offline Operations',
            ]);
            return;
        }

        if (!(new OfflineAuthorizationService())->canManageSync()
            && !(function_exists('rateb_can') && rateb_can('pos.sync.manage'))
            && !(function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            http_response_code(403);
            $this->view('offline/ops/forbidden', ['title' => 'Forbidden']);
            return;
        }

        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = (int) rateb_resolve_ops_company_id();
        }

        $snap = (new OfflineMonitoringService())->snapshot($companyId > 0 ? $companyId : null);
        $this->view('offline/ops/index', [
            'title' => 'Offline Operations',
            'snap' => $snap,
            'metricsApi' => rateb_url('api/v1/offline/monitoring'),
        ]);
    }

    private function requireCompany(): void
    {
        if ((int) (TenantContext::companyId() ?? 0) > 0) {
            return;
        }
        if (function_exists('rateb_resolve_ops_company_id') && rateb_resolve_ops_company_id() > 0) {
            return;
        }
    }
}
