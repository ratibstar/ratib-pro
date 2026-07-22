<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ApprovalOversightService;
use Rateb\App\Services\DashboardService;

/** Async charts + recent panels for admin dashboard (faster HTML first paint). */
final class DashboardChartsController
{
    public function index(): void
    {
        if (!Auth::check()) {
            Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            Response::json(['ok' => false, 'error' => 'forbidden'], 403);
            return;
        }
        if (function_exists('rateb_is_platform_oversight_host') && !rateb_is_platform_oversight_host()) {
            Response::json(['ok' => false, 'error' => 'forbidden'], 403);
            return;
        }

        try {
            $panels = (new DashboardService())->adminDeferredPanels();
            $menuCounts = $this->warmOversightMenuCounts();
            if ($menuCounts !== null) {
                $panels['menu_counts'] = $menuCounts;
            }
            Response::json(['ok' => true] + $panels);
        } catch (\Throwable $e) {
            error_log('DashboardChartsController: ' . $e->getMessage());
            Response::json(['ok' => false, 'error' => 'server_error'], 500);
        }
    }

    /** @return array<string, int>|null */
    private function warmOversightMenuCounts(): ?array
    {
        try {
            $counts = (new ApprovalOversightService())->menuCounts(null);
            SessionManager::set('rateb_oversight_menu_counts', [
                'exp' => time() + 300,
                'data' => $counts,
            ]);

            return $counts;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
