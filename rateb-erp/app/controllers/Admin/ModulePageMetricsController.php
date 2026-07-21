<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;

/** Async KPI strip for module list pages (faster HTML first paint). */
final class ModulePageMetricsController
{
    public function index(): void
    {
        if (!Auth::check()) {
            Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }

        $route = trim((string) ($_GET['route'] ?? ''));
        if ($route === '' && function_exists('rateb_current_erp_route')) {
            $route = rateb_current_erp_route();
        }
        $route = trim($route, '/');
        if ($route === '' || !function_exists('rateb_module_page_metrics')) {
            Response::json(['ok' => true, 'metrics' => []]);
            return;
        }

        // Honor ?company_id= for super-admin ops company picker (same as list pages).
        $companyId = (int) ($_GET['company_id'] ?? 0);
        if ($companyId > 0 && function_exists('rateb_adopt_ops_company_id')) {
            rateb_adopt_ops_company_id($companyId);
            \Rateb\App\Core\SessionManager::set('rateb_ops_company_id', $companyId);
            \Rateb\App\Core\TenantContext::setCompanyId($companyId);
        }

        if (!class_exists(\Rateb\App\Services\ModulePageStatsService::class)) {
            Response::json(['ok' => true, 'metrics' => []]);
            return;
        }

        $service = new \Rateb\App\Services\ModulePageStatsService();
        if (!$service->routeSupportsMetrics($route)) {
            Response::json(['ok' => true, 'metrics' => []]);
            return;
        }

        Response::json([
            'ok' => true,
            'metrics' => rateb_module_page_metrics($route),
        ]);
    }
}
