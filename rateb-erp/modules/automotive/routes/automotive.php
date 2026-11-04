<?php
declare(strict_types=1);

/**
 * Automotive Admin routes — Phase 1 shell only.
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Automotive\Controllers\AutomotiveDashboardController;
use Rateb\App\Automotive\Controllers\AutomotivePartsController;
use Rateb\App\Automotive\Controllers\AutomotiveReferenceController;
use Rateb\App\Automotive\Controllers\AutomotiveVehiclesController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$autoApp = static fn (string $sub = '') => '/' . rateb_app_route($sub === '' ? 'automotive' : 'automotive/' . ltrim($sub, '/'));
$autoMw = static fn (string $entity = 'automotive', string $permission = '') => rateb_erp_mw('automotive', $permission, $entity);

$router->get($autoApp(''), [AutomotiveDashboardController::class, 'index'], $autoMw());
$router->get($autoApp('dashboard'), [AutomotiveDashboardController::class, 'index'], $autoMw());
$router->get($autoApp('parts'), [AutomotivePartsController::class, 'index'], $autoMw());
$router->post($autoApp('parts'), [AutomotivePartsController::class, 'store'], $autoMw());
$router->get($autoApp('parts/{id}'), [AutomotivePartsController::class, 'show'], $autoMw());
$router->post($autoApp('parts/{id}/numbers'), [AutomotivePartsController::class, 'storeNumber'], $autoMw());
$router->post($autoApp('parts/{id}/barcodes'), [AutomotivePartsController::class, 'storeBarcode'], $autoMw());
$router->post($autoApp('parts/{id}/fitment'), [AutomotivePartsController::class, 'storeFitment'], $autoMw());
$router->post($autoApp('parts/{id}/interchange'), [AutomotivePartsController::class, 'storeInterchange'], $autoMw());
$router->get($autoApp('reference'), [AutomotiveReferenceController::class, 'index'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/markets'), [AutomotiveReferenceController::class, 'storeMarket'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/organizations'), [AutomotiveReferenceController::class, 'storeOrganization'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/brands'), [AutomotiveReferenceController::class, 'storeBrand'], $autoMw('automotive_reference'));
$router->get($autoApp('structure'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'index'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/structure/models'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'storeModel'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/structure/generations'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'storeGeneration'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/structure/engines'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'storeEngine'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/structure/transmissions'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'storeTransmission'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/structure/diagrams'), [\Rateb\App\Automotive\Controllers\AutomotiveStructureController::class, 'storeDiagram'], $autoMw('automotive_reference'));
$router->get($autoApp('catalog'), [\Rateb\App\Automotive\Controllers\AutomotiveCatalogController::class, 'index'], $autoMw());
$router->get($autoApp('selection'), [\Rateb\App\Automotive\Controllers\AutomotiveSelectionController::class, 'index'], $autoMw('automotive_selection'));
$router->post($autoApp('selection'), [\Rateb\App\Automotive\Controllers\AutomotiveSelectionController::class, 'store'], $autoMw('automotive_selection'));
$router->post($autoApp('selection/remove'), [\Rateb\App\Automotive\Controllers\AutomotiveSelectionController::class, 'remove'], $autoMw('automotive_selection'));
$router->post($autoApp('links'), [\Rateb\App\Automotive\Controllers\AutomotiveSelectionController::class, 'link'], $autoMw('automotive_link'));
$router->post($autoApp('supplier-maps'), [\Rateb\App\Automotive\Controllers\AutomotiveSelectionController::class, 'mapSupplier'], $autoMw('automotive_supplier'));
$router->post($autoApp('reference/parts'), [\Rateb\App\Automotive\Controllers\AutomotiveCatalogController::class, 'storePart'], $autoMw('automotive_reference'));
$router->post($autoApp('reference/parts/retire'), [\Rateb\App\Automotive\Controllers\AutomotiveCatalogController::class, 'retirePart'], $autoMw('', 'automotive.reference.retire'));
$router->post($autoApp('reference/imports'), [\Rateb\App\Automotive\Controllers\AutomotiveCatalogController::class, 'previewImport'], $autoMw('', 'automotive.reference.import'));
$router->post($autoApp('reference/imports/approve'), [\Rateb\App\Automotive\Controllers\AutomotiveCatalogController::class, 'approveImport'], $autoMw('', 'automotive.reference.approve'));
$router->get($autoApp('vehicles'), [AutomotiveVehiclesController::class, 'index'], $autoMw());
$router->post($autoApp('vehicles'), [AutomotiveVehiclesController::class, 'store'], $autoMw());
