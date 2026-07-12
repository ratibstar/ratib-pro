<?php

declare(strict_types=1);

use Rateb\App\Offline\Controllers\OfflineMonitoringApiController;
use Rateb\App\Offline\Middleware\OfflineApiAuthMiddleware;

/** @var Rateb\App\Core\Router $router */

/**
 * Additive read-only monitoring API (Phase 6).
 * Does not alter /api/v1/offline/push|process|conflicts resolve.
 */
$offlineApi = [OfflineApiAuthMiddleware::class];

$router->get('/api/v1/offline/monitoring', [OfflineMonitoringApiController::class, 'overview'], $offlineApi);
$router->get('/api/v1/offline/monitoring/queue', [OfflineMonitoringApiController::class, 'queue'], $offlineApi);
$router->get('/api/v1/offline/monitoring/devices', [OfflineMonitoringApiController::class, 'devices'], $offlineApi);
$router->get('/api/v1/offline/monitoring/conflicts', [OfflineMonitoringApiController::class, 'conflicts'], $offlineApi);
$router->get('/api/v1/offline/monitoring/alerts', [OfflineMonitoringApiController::class, 'alerts'], $offlineApi);
$router->get('/api/v1/offline/monitoring/readiness', [OfflineMonitoringApiController::class, 'readiness'], $offlineApi);
