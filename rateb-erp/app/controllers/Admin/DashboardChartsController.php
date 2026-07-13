<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;
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
            Response::json(['ok' => true] + $panels);
        } catch (\Throwable $e) {
            error_log('DashboardChartsController: ' . $e->getMessage());
            Response::json(['ok' => false, 'error' => 'server_error'], 500);
        }
    }
}
