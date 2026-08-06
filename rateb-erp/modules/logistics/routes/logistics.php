<?php
declare(strict_types=1);

/**
 * Logistics Admin routes — Phases 2–5 (Admin CRUD + reports; no mobile app).
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Logistics\Controllers\LogisticsDashboardController;
use Rateb\App\Logistics\Controllers\LogisticsDriversController;
use Rateb\App\Logistics\Controllers\LogisticsExpensesController;
use Rateb\App\Logistics\Controllers\LogisticsFleetController;
use Rateb\App\Logistics\Controllers\LogisticsReportsController;
use Rateb\App\Logistics\Controllers\LogisticsRoutesController;
use Rateb\App\Logistics\Controllers\LogisticsShipmentsController;
use Rateb\App\Logistics\Controllers\LogisticsTripsController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$logApp = static fn (string $sub = '') => '/' . rateb_app_route($sub === '' ? 'logistics' : 'logistics/' . ltrim($sub, '/'));
$logMw = static fn (string $entity = 'logistics') => rateb_erp_mw('logistics', '', $entity);

$router->get($logApp(''), [LogisticsDashboardController::class, 'index'], $logMw());
$router->get($logApp('dashboard'), [LogisticsDashboardController::class, 'index'], $logMw());

$vehMw = $logMw('logistics/vehicles');
$router->get($logApp('vehicles'), [LogisticsFleetController::class, 'index'], $vehMw);
$router->get($logApp('vehicles/create'), [LogisticsFleetController::class, 'create'], $vehMw);
$router->post($logApp('vehicles'), [LogisticsFleetController::class, 'store'], $vehMw);
$router->get($logApp('vehicles/{id}/edit'), [LogisticsFleetController::class, 'edit'], $vehMw);
$router->post($logApp('vehicles/{id}'), [LogisticsFleetController::class, 'update'], $vehMw);
$router->post($logApp('vehicles/{id}/delete'), [LogisticsFleetController::class, 'destroy'], $vehMw);

$drvMw = $logMw('logistics/drivers');
$router->get($logApp('drivers'), [LogisticsDriversController::class, 'index'], $drvMw);
$router->get($logApp('drivers/create'), [LogisticsDriversController::class, 'create'], $drvMw);
$router->post($logApp('drivers'), [LogisticsDriversController::class, 'store'], $drvMw);
$router->get($logApp('drivers/{id}/edit'), [LogisticsDriversController::class, 'edit'], $drvMw);
$router->post($logApp('drivers/{id}'), [LogisticsDriversController::class, 'update'], $drvMw);
$router->post($logApp('drivers/{id}/delete'), [LogisticsDriversController::class, 'destroy'], $drvMw);

$tripMw = $logMw('logistics/trips');
$router->get($logApp('trips'), [LogisticsTripsController::class, 'index'], $tripMw);
$router->get($logApp('trips/create'), [LogisticsTripsController::class, 'create'], $tripMw);
$router->post($logApp('trips'), [LogisticsTripsController::class, 'store'], $tripMw);
$router->get($logApp('trips/{id}/edit'), [LogisticsTripsController::class, 'edit'], $tripMw);
$router->post($logApp('trips/{id}'), [LogisticsTripsController::class, 'update'], $tripMw);
$router->post($logApp('trips/{id}/transition'), [LogisticsTripsController::class, 'transition'], $tripMw);
$router->post($logApp('trips/{id}/delete'), [LogisticsTripsController::class, 'destroy'], $tripMw);

$shipMw = $logMw('logistics/shipments');
$router->get($logApp('shipments'), [LogisticsShipmentsController::class, 'index'], $shipMw);
$router->get($logApp('shipments/create'), [LogisticsShipmentsController::class, 'create'], $shipMw);
$router->post($logApp('shipments'), [LogisticsShipmentsController::class, 'store'], $shipMw);
$router->get($logApp('shipments/{id}/edit'), [LogisticsShipmentsController::class, 'edit'], $shipMw);
$router->post($logApp('shipments/{id}'), [LogisticsShipmentsController::class, 'update'], $shipMw);
$router->post($logApp('shipments/{id}/transition'), [LogisticsShipmentsController::class, 'transition'], $shipMw);
$router->post($logApp('shipments/{id}/dispatch'), [LogisticsShipmentsController::class, 'dispatch'], $shipMw);
$router->post($logApp('shipments/{id}/delete'), [LogisticsShipmentsController::class, 'destroy'], $shipMw);

$routeMw = $logMw('logistics/routes');
$router->get($logApp('routes'), [LogisticsRoutesController::class, 'index'], $routeMw);
$router->get($logApp('routes/create'), [LogisticsRoutesController::class, 'create'], $routeMw);
$router->post($logApp('routes'), [LogisticsRoutesController::class, 'store'], $routeMw);
$router->get($logApp('routes/{id}/edit'), [LogisticsRoutesController::class, 'edit'], $routeMw);
$router->post($logApp('routes/{id}'), [LogisticsRoutesController::class, 'update'], $routeMw);
$router->post($logApp('routes/{id}/delete'), [LogisticsRoutesController::class, 'destroy'], $routeMw);

$expMw = $logMw('logistics/expenses');
$router->get($logApp('expenses'), [LogisticsExpensesController::class, 'index'], $expMw);
$router->get($logApp('expenses/create'), [LogisticsExpensesController::class, 'create'], $expMw);
$router->post($logApp('expenses'), [LogisticsExpensesController::class, 'store'], $expMw);
$router->get($logApp('expenses/{id}/edit'), [LogisticsExpensesController::class, 'edit'], $expMw);
$router->post($logApp('expenses/{id}'), [LogisticsExpensesController::class, 'update'], $expMw);
$router->post($logApp('expenses/{id}/post'), [LogisticsExpensesController::class, 'post'], $expMw);
$router->post($logApp('expenses/{id}/delete'), [LogisticsExpensesController::class, 'destroy'], $expMw);

$repMw = $logMw('logistics/reports');
$router->get($logApp('reports'), [LogisticsReportsController::class, 'index'], $repMw);
$router->get($logApp('reports/{type}'), [LogisticsReportsController::class, 'show'], $repMw);
