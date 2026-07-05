<?php
declare(strict_types=1);

use Rateb\App\Pos\Controllers\PosApiController;
use Rateb\App\Pos\Controllers\PosOrderOpsApiController;
use Rateb\App\Pos\Controllers\PosRegisterApiController;
use Rateb\App\Pos\Controllers\PosReportsController;
use Rateb\App\Core\Middleware\ApiModuleMiddleware;
use Rateb\App\Core\Middleware\ApiAuthMiddleware;

/** @var Rateb\App\Core\Router $router */

$posApi = [ApiAuthMiddleware::class, [ApiModuleMiddleware::class, 'pos']];

$router->get('/api/v1/pos/context', [PosApiController::class, 'context'], $posApi);
$router->get('/api/v1/pos/sync/status', [PosApiController::class, 'syncStatus'], $posApi);
$router->post('/api/v1/pos/sync/push', [PosApiController::class, 'syncPush'], $posApi);
$router->get('/api/v1/pos/pricing/preview', [PosApiController::class, 'pricingPreview'], $posApi);

$router->get('/api/v1/pos/register/session', [PosRegisterApiController::class, 'sessionGet'], $posApi);
$router->post('/api/v1/pos/register/session', [PosRegisterApiController::class, 'sessionSave'], $posApi);
$router->get('/api/v1/pos/register/customers/search', [PosRegisterApiController::class, 'searchCustomers'], $posApi);
$router->get('/api/v1/pos/register/products/search', [PosRegisterApiController::class, 'searchProducts'], $posApi);
$router->get('/api/v1/pos/register/barcode', [PosRegisterApiController::class, 'lookupBarcode'], $posApi);
$router->post('/api/v1/pos/register/pricing', [PosRegisterApiController::class, 'pricingPreview'], $posApi);
$router->post('/api/v1/pos/register/checkout', [PosRegisterApiController::class, 'checkout'], $posApi);
$router->post('/api/v1/pos/register/cart/add', [PosRegisterApiController::class, 'cartAdd'], $posApi);
$router->post('/api/v1/pos/register/coupon/validate', [PosRegisterApiController::class, 'validateCoupon'], $posApi);
$router->post('/api/v1/pos/register/gift-card/validate', [PosRegisterApiController::class, 'validateGiftCard'], $posApi);
$router->get('/api/v1/pos/register/loyalty/balance', [PosRegisterApiController::class, 'loyaltyBalance'], $posApi);
$router->post('/api/v1/pos/register/cart/update-line', [PosRegisterApiController::class, 'cartUpdateLine'], $posApi);
$router->get('/api/v1/pos/register/products/detail', [PosRegisterApiController::class, 'productDetail'], $posApi);
$router->get('/api/v1/pos/register/products/availability', [PosRegisterApiController::class, 'productAvailability'], $posApi);
$router->get('/api/v1/pos/register/products/fefo-preview', [PosRegisterApiController::class, 'productFefoPreview'], $posApi);
$router->get('/api/v1/pos/register/products/serials', [PosRegisterApiController::class, 'productSerials'], $posApi);

$router->post('/api/v1/pos/register/suspend', [PosOrderOpsApiController::class, 'suspend'], $posApi);
$router->get('/api/v1/pos/register/suspended', [PosOrderOpsApiController::class, 'suspendedList'], $posApi);
$router->post('/api/v1/pos/register/suspended/{id}/resume', [PosOrderOpsApiController::class, 'resumeSuspended'], $posApi);
$router->post('/api/v1/pos/register/quote/save', [PosOrderOpsApiController::class, 'saveQuote'], $posApi);
$router->get('/api/v1/pos/register/quotes', [PosOrderOpsApiController::class, 'quotesList'], $posApi);
$router->post('/api/v1/pos/register/quotes/{id}/convert', [PosOrderOpsApiController::class, 'convertQuote'], $posApi);
$router->get('/api/v1/pos/register/orders/search', [PosOrderOpsApiController::class, 'searchOrdersForReturn'], $posApi);
$router->get('/api/v1/pos/register/orders/{id}/returnable-lines', [PosOrderOpsApiController::class, 'returnableLines'], $posApi);
$router->post('/api/v1/pos/register/return', [PosOrderOpsApiController::class, 'processReturn'], $posApi);
$router->post('/api/v1/pos/register/exchange', [PosOrderOpsApiController::class, 'processExchange'], $posApi);

$router->get('/api/v1/pos/reports/shifts/{id}/x', [PosReportsController::class, 'xReportJson'], $posApi);
