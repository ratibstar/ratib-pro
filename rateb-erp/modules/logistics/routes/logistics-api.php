<?php
declare(strict_types=1);

/**
 * Logistics Driver API — Phase 4
 * Auth: ApiAuthMiddleware + logistics plan module.
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Core\Middleware\ApiAuthMiddleware;
use Rateb\App\Core\Middleware\ApiModuleMiddleware;
use Rateb\App\Logistics\Controllers\Api\DriverLocationController;
use Rateb\App\Logistics\Controllers\Api\DriverShipmentController;
use Rateb\App\Logistics\Controllers\Api\DriverTripsController;
use Rateb\App\Logistics\Controllers\Api\ProofOfDeliveryController;

$logisticsApi = [ApiAuthMiddleware::class, [ApiModuleMiddleware::class, 'logistics']];

$router->get('/api/v1/logistics/driver/trips', [DriverTripsController::class, 'index'], $logisticsApi);
$router->post('/api/v1/logistics/driver/trips/{id}/start', [DriverTripsController::class, 'start'], $logisticsApi);
$router->post('/api/v1/logistics/driver/trips/{id}/complete', [DriverTripsController::class, 'complete'], $logisticsApi);

$router->post('/api/v1/logistics/driver/location', [DriverLocationController::class, 'update'], $logisticsApi);

$router->post('/api/v1/logistics/driver/shipments/{id}/deliver', [DriverShipmentController::class, 'deliver'], $logisticsApi);
$router->post('/api/v1/logistics/driver/shipments/{id}/pod', [ProofOfDeliveryController::class, 'upload'], $logisticsApi);
