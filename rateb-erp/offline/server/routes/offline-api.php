<?php

declare(strict_types=1);

use Rateb\App\Offline\Controllers\OfflineSyncApiController;
use Rateb\App\Offline\Controllers\ErpOfflineAuthApiController;
use Rateb\App\Offline\Controllers\ErpOfflineDeviceTrustApiController;
use Rateb\App\Offline\Controllers\ErpOfflineRbacApiController;
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

/** Phase 11 — ERP offline auth device enroll (flag-gated in controller). */
$router->get('/api/v1/offline/auth/policy', [ErpOfflineAuthApiController::class, 'policy'], $offlineApi);
$router->post('/api/v1/offline/auth/device/register', [ErpOfflineAuthApiController::class, 'deviceRegister'], $offlineApi);
$router->post('/api/v1/offline/auth/device/heartbeat', [ErpOfflineAuthApiController::class, 'deviceHeartbeat'], $offlineApi);
$router->post('/api/v1/offline/auth/identity/enroll', [ErpOfflineAuthApiController::class, 'identityEnroll'], $offlineApi);

/** Phase P2 — device trust / renew APIs (flag + permission gated in controller). */
$router->get('/api/v1/offline/devices', [ErpOfflineDeviceTrustApiController::class, 'devices'], $offlineApi);
$router->post('/api/v1/offline/devices/rename', [ErpOfflineDeviceTrustApiController::class, 'rename'], $offlineApi);
$router->post('/api/v1/offline/devices/revoke', [ErpOfflineDeviceTrustApiController::class, 'revoke'], $offlineApi);
$router->post('/api/v1/offline/devices/renew', [ErpOfflineDeviceTrustApiController::class, 'renew'], $offlineApi);
$router->post('/api/v1/offline/devices/logout-device', [ErpOfflineDeviceTrustApiController::class, 'logoutDevice'], $offlineApi);
$router->post('/api/v1/offline/devices/revoke-all', [ErpOfflineDeviceTrustApiController::class, 'revokeAll'], $offlineApi);
$router->post('/api/v1/offline/devices/restore', [ErpOfflineDeviceTrustApiController::class, 'restore'], $offlineApi);

/** Phase 12 — ERP offline RBAC/nav manifest (flag-gated in controller; UI cache only). */
$router->get('/api/v1/offline/rbac/version', [ErpOfflineRbacApiController::class, 'version'], $offlineApi);
$router->get('/api/v1/offline/rbac/manifest', [ErpOfflineRbacApiController::class, 'manifest'], $offlineApi);
