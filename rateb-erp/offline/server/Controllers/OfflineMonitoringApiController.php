<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflineMonitoringService;

/**
 * Read-only Offline Monitoring API — /api/v1/offline/monitoring/*
 * Additive; does not alter push/process/resolve contracts.
 */
final class OfflineMonitoringApiController extends Controller
{
    public function overview(): void
    {
        $this->gate();
        $snap = (new OfflineMonitoringService())->snapshot($this->companyId());
        $this->json(['ok' => true, 'monitoring' => $snap]);
    }

    public function queue(): void
    {
        $this->gate();
        $this->json(['ok' => true, 'queue_health' => (new OfflineMonitoringService())->queueHealth($this->companyId())]);
    }

    public function devices(): void
    {
        $this->gate();
        $this->json(['ok' => true, 'devices' => (new OfflineMonitoringService())->deviceStatus($this->companyId())]);
    }

    public function conflicts(): void
    {
        $this->gate();
        $this->json(['ok' => true, 'conflicts' => (new OfflineMonitoringService())->conflictDashboard($this->companyId())]);
    }

    public function alerts(): void
    {
        $this->gate();
        $svc = new OfflineMonitoringService();
        $cid = $this->companyId();
        $qh = $svc->queueHealth($cid);
        $alerts = $svc->alerts($cid, $qh, $svc->deviceStatus($cid), $svc->conflictDashboard($cid), $svc->retryDashboard($cid));
        $this->json(['ok' => true, 'alerts' => $alerts]);
    }

    public function readiness(): void
    {
        $this->gate();
        $svc = new OfflineMonitoringService();
        $cid = $this->companyId();
        $qh = $svc->queueHealth($cid);
        $this->json([
            'ok' => true,
            'production_readiness' => $svc->productionReadiness($cid, $qh, $svc->deviceStatus($cid), $svc->conflictDashboard($cid)),
            'performance' => $svc->performanceMetrics($cid, $svc->synchronizationMetrics($cid)),
        ]);
    }

    private function gate(): void
    {
        $this->requireAuthOrAbort();
        $this->requireManageOrAbort();
        $this->requireMonitoringOrAbort();
    }

    private function requireMonitoringOrAbort(): void
    {
        if ((new OfflineFeatureFlagService())->isMonitoringEnabled()) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'monitoring_disabled', 'code' => 'monitoring_disabled'],
        ], 403);
        exit;
    }

    private function requireManageOrAbort(): void
    {
        if ((new OfflineAuthorizationService())->canManageSync()) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'Forbidden', 'code' => 'forbidden'],
        ], 403);
        exit;
    }

    private function requireAuthOrAbort(): void
    {
        if ((new OfflineAuthorizationService())->isAuthenticatedCompany() || $this->companyId() > 0) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'Unauthorized', 'code' => 'unauthorized'],
        ], 401);
        exit;
    }

    private function companyId(): int
    {
        $fromTenant = (int) (TenantContext::companyId() ?? 0);
        if ($fromTenant > 0) {
            return $fromTenant;
        }

        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }
}
