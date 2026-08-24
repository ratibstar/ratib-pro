<?php
declare(strict_types=1);

/**
 * Dashboard-only routes (Phase AA.3).
 * Moved from routes/web.php — definitions unchanged.
 */

use Rateb\App\Controllers\Admin\DashboardController as AdminDashboardController;
use Rateb\App\Controllers\Admin\ModulePageMetricsController;
use Rateb\App\Controllers\Admin\SupportTicketAlertsApiController;
use Rateb\App\Controllers\Admin\ExecutiveDashboardController;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$router->get('/admin/api/module-metrics', [ModulePageMetricsController::class, 'index'], [ErpAuthMiddleware::class]);
$router->get('/admin/api/support-ticket-alerts', [SupportTicketAlertsApiController::class, 'poll'], [ErpAuthMiddleware::class]);
$router->post('/admin/api/support-ticket-alerts/seen', [SupportTicketAlertsApiController::class, 'markSeen'], [ErpAuthMiddleware::class]);
$router->get('/admin/api/dashboard-charts', [\Rateb\App\Controllers\Admin\DashboardChartsController::class, 'index'], [ErpAuthMiddleware::class]);
$router->get('/admin', [AdminDashboardController::class, 'index'], [ErpAuthMiddleware::class]);
$router->get('/admin/executive-dashboard', [ExecutiveDashboardController::class, 'index'], rateb_admin_mw('executive.dashboard.view'));
