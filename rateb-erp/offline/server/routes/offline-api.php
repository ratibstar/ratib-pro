<?php

declare(strict_types=1);

use Rateb\App\Offline\Controllers\OfflineSyncApiController;
use Rateb\App\Core\Middleware\ApiAuthMiddleware;

/** @var Rateb\App\Core\Router $router */

/**
 * Additive enterprise offline sync API (Phase 2A).
 * Does not replace or alter /api/v1/pos/sync/*.
 */
$offlineApi = [ApiAuthMiddleware::class];

$router->get('/api/v1/offline/status', [OfflineSyncApiController::class, 'status'], $offlineApi);
$router->post('/api/v1/offline/push', [OfflineSyncApiController::class, 'push'], $offlineApi);
$router->post('/api/v1/offline/process', [OfflineSyncApiController::class, 'process'], $offlineApi);
$router->get('/api/v1/offline/conflicts', [OfflineSyncApiController::class, 'conflicts'], $offlineApi);
$router->post('/api/v1/offline/conflicts/{id}/resolve', [OfflineSyncApiController::class, 'resolveConflict'], $offlineApi);
$router->get('/api/v1/offline/delta/{entity}', [OfflineSyncApiController::class, 'delta'], $offlineApi);
