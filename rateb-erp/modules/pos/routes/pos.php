<?php
declare(strict_types=1);

use Rateb\App\Pos\Controllers\PosApiController;
use Rateb\App\Pos\Controllers\PosCashDrawersController;
use Rateb\App\Pos\Controllers\PosDashboardController;
use Rateb\App\Pos\Controllers\PosOrdersController;
use Rateb\App\Pos\Controllers\PosReportsController;
use Rateb\App\Pos\Controllers\PosRegisterController;
use Rateb\App\Pos\Controllers\PosRegisterApiController;
use Rateb\App\Pos\Controllers\PosSettingsController;
use Rateb\App\Pos\Controllers\PosShiftsController;
use Rateb\App\Pos\Controllers\PosSyncController;
use Rateb\App\Pos\Controllers\PosOrderOpsApiController;
use Rateb\App\Pos\Controllers\PosReturnsController;
use Rateb\App\Pos\Controllers\PosTerminalsController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$posApp = static fn (string $sub = ''): string => '/' . rateb_app_route($sub === '' ? 'pos' : 'pos/' . ltrim($sub, '/'));

$posMw = static fn (string $entity = 'pos') => rateb_erp_mw('pos', '', $entity);

$router->get($posApp('dashboard'), [PosDashboardController::class, 'index'], $posMw('pos'));
$router->get($posApp(''), [PosRegisterController::class, 'index'], $posMw('pos/register'));
$router->get($posApp('register'), [PosRegisterController::class, 'index'], $posMw('pos/register'));

$termMw = $posMw('pos/terminals');
$router->get($posApp('terminals'), [PosTerminalsController::class, 'index'], $termMw);
$router->get($posApp('terminals/create'), [PosTerminalsController::class, 'create'], $termMw);
$router->post($posApp('terminals'), [PosTerminalsController::class, 'store'], $termMw);
$router->get($posApp('terminals/{id}/edit'), [PosTerminalsController::class, 'edit'], $termMw);
$router->post($posApp('terminals/{id}'), [PosTerminalsController::class, 'update'], $termMw);
$router->post($posApp('terminals/{id}/delete'), [PosTerminalsController::class, 'destroy'], $termMw);

$shiftMw = $posMw('pos/shifts');
$router->get($posApp('shifts'), [PosShiftsController::class, 'index'], $shiftMw);
$router->get($posApp('shifts/open'), [PosShiftsController::class, 'openForm'], $shiftMw);
$router->post($posApp('shifts/open'), [PosShiftsController::class, 'openStore'], $shiftMw);
$router->get($posApp('shifts/{id}'), [PosShiftsController::class, 'show'], $shiftMw);
$router->get($posApp('shifts/{id}/close'), [PosShiftsController::class, 'closeForm'], $shiftMw);
$router->post($posApp('shifts/{id}/close'), [PosShiftsController::class, 'closeStore'], $shiftMw);

$reportsMw = $posMw('pos/reports');
$router->get($posApp('reports'), [PosReportsController::class, 'index'], $reportsMw);
$router->get($posApp('reports/shifts/{id}/x'), [PosReportsController::class, 'xReport'], $reportsMw);
$router->get($posApp('reports/snapshots/{id}/z'), [PosReportsController::class, 'zReport'], $reportsMw);
$router->get($posApp('api/reports/shifts/{id}/x'), [PosReportsController::class, 'xReportJson'], $reportsMw);

$drawerMw = $posMw('pos/cash-drawers');
$router->get($posApp('cash-drawers'), [PosCashDrawersController::class, 'index'], $drawerMw);
$router->get($posApp('cash-drawers/{id}'), [PosCashDrawersController::class, 'show'], $drawerMw);
$router->post($posApp('cash-drawers/{id}/event'), [PosCashDrawersController::class, 'storeEvent'], $drawerMw);

$router->get($posApp('orders'), [PosOrdersController::class, 'index'], $posMw('pos/orders'));
$router->get($posApp('orders/{id}'), [PosOrdersController::class, 'show'], $posMw('pos/orders'));

$returnsMw = $posMw('pos/returns');
$router->get($posApp('returns'), [PosReturnsController::class, 'index'], $returnsMw);

$opsMw = $posMw('pos/register');
$router->post($posApp('api/register/suspend'), [PosOrderOpsApiController::class, 'suspend'], $opsMw);
$router->get($posApp('api/register/suspended'), [PosOrderOpsApiController::class, 'suspendedList'], $opsMw);
$router->post($posApp('api/register/suspended/{id}/resume'), [PosOrderOpsApiController::class, 'resumeSuspended'], $opsMw);
$router->post($posApp('api/register/quote/save'), [PosOrderOpsApiController::class, 'saveQuote'], $opsMw);
$router->get($posApp('api/register/quotes'), [PosOrderOpsApiController::class, 'quotesList'], $opsMw);
$router->post($posApp('api/register/quotes/{id}/convert'), [PosOrderOpsApiController::class, 'convertQuote'], $opsMw);
$router->get($posApp('api/register/orders/search'), [PosOrderOpsApiController::class, 'searchOrdersForReturn'], $returnsMw);
$router->get($posApp('api/register/orders/{id}/returnable-lines'), [PosOrderOpsApiController::class, 'returnableLines'], $returnsMw);
$router->post($posApp('api/register/return'), [PosOrderOpsApiController::class, 'processReturn'], $returnsMw);
$router->post($posApp('api/register/exchange'), [PosOrderOpsApiController::class, 'processExchange'], $returnsMw);

$router->get($posApp('settings'), [PosSettingsController::class, 'index'], $posMw('pos/settings'));
$router->get($posApp('sync'), [PosSyncController::class, 'index'], $posMw('pos/sync'));

$router->get($posApp('api/context'), [PosApiController::class, 'context'], $posMw('pos'));
$router->get($posApp('api/sync/status'), [PosApiController::class, 'syncStatus'], $posMw('pos/sync'));
$router->post($posApp('api/sync/push'), [PosApiController::class, 'syncPush'], $posMw('pos/sync'));
$router->get($posApp('api/pricing/preview'), [PosApiController::class, 'pricingPreview'], $posMw('pos/register'));

$regMw = $posMw('pos/register');
$router->get($posApp('api/register/session'), [PosRegisterApiController::class, 'sessionGet'], $regMw);
$router->post($posApp('api/register/session'), [PosRegisterApiController::class, 'sessionSave'], $regMw);
$router->get($posApp('api/register/customers/search'), [PosRegisterApiController::class, 'searchCustomers'], $regMw);
$router->get($posApp('api/register/products/search'), [PosRegisterApiController::class, 'searchProducts'], $regMw);
$router->get($posApp('api/register/barcode'), [PosRegisterApiController::class, 'lookupBarcode'], $regMw);
$router->post($posApp('api/register/pricing'), [PosRegisterApiController::class, 'pricingPreview'], $regMw);
$router->post($posApp('api/register/checkout'), [PosRegisterApiController::class, 'checkout'], $regMw);
$router->post($posApp('api/register/cart/add'), [PosRegisterApiController::class, 'cartAdd'], $regMw);
$router->post($posApp('api/register/cart/update-line'), [PosRegisterApiController::class, 'cartUpdateLine'], $regMw);
$router->post($posApp('api/register/coupon/validate'), [PosRegisterApiController::class, 'validateCoupon'], $regMw);
$router->post($posApp('api/register/gift-card/validate'), [PosRegisterApiController::class, 'validateGiftCard'], $regMw);
$router->get($posApp('api/register/loyalty/balance'), [PosRegisterApiController::class, 'loyaltyBalance'], $regMw);
$router->get($posApp('api/register/products/detail'), [PosRegisterApiController::class, 'productDetail'], $regMw);
$router->get($posApp('api/register/products/availability'), [PosRegisterApiController::class, 'productAvailability'], $regMw);
$router->get($posApp('api/register/products/fefo-preview'), [PosRegisterApiController::class, 'productFefoPreview'], $regMw);
$router->get($posApp('api/register/products/serials'), [PosRegisterApiController::class, 'productSerials'], $regMw);
