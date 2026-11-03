<?php
declare(strict_types=1);

/**
 * Automotive Admin routes — Phase 1 shell only.
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Automotive\Controllers\AutomotiveDashboardController;
use Rateb\App\Automotive\Controllers\AutomotivePartsController;
use Rateb\App\Automotive\Controllers\AutomotiveVehiclesController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$autoApp = static fn (string $sub = '') => '/' . rateb_app_route($sub === '' ? 'automotive' : 'automotive/' . ltrim($sub, '/'));
$autoMw = static fn (string $entity = 'automotive') => rateb_erp_mw('automotive', '', $entity);

$router->get($autoApp(''), [AutomotiveDashboardController::class, 'index'], $autoMw());
$router->get($autoApp('dashboard'), [AutomotiveDashboardController::class, 'index'], $autoMw());
$router->get($autoApp('parts'), [AutomotivePartsController::class, 'index'], $autoMw());
$router->post($autoApp('parts'), [AutomotivePartsController::class, 'store'], $autoMw());
$router->get($autoApp('parts/{id}'), [AutomotivePartsController::class, 'show'], $autoMw());
$router->post($autoApp('parts/{id}/numbers'), [AutomotivePartsController::class, 'storeNumber'], $autoMw());
$router->post($autoApp('parts/{id}/barcodes'), [AutomotivePartsController::class, 'storeBarcode'], $autoMw());
$router->post($autoApp('parts/{id}/fitment'), [AutomotivePartsController::class, 'storeFitment'], $autoMw());
$router->post($autoApp('parts/{id}/interchange'), [AutomotivePartsController::class, 'storeInterchange'], $autoMw());
$router->get($autoApp('vehicles'), [AutomotiveVehiclesController::class, 'index'], $autoMw());
$router->post($autoApp('vehicles'), [AutomotiveVehiclesController::class, 'store'], $autoMw());
