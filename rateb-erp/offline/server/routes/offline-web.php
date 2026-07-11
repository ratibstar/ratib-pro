<?php

declare(strict_types=1);

use Rateb\App\Offline\Controllers\OfflineOpsDashboardController;

/** @var Rateb\App\Core\Router $router */

/**
 * Additive Offline Operations web routes (Phase 6) — read-only dashboard.
 */
$app = static fn (string $sub): string => '/' . rateb_app_route($sub);
$mw = rateb_erp_mw('', 'pos.sync.manage');

$router->get($app('offline/ops'), [OfflineOpsDashboardController::class, 'index'], $mw);
$router->get($app('offline/monitoring'), [OfflineOpsDashboardController::class, 'index'], $mw);
