<?php
declare(strict_types=1);

use Rateb\App\Controllers\Api\ApiController;
use Rateb\App\Core\Middleware\ApiAuthMiddleware;

/** @var Rateb\App\Core\Router $router */

$api = [ApiAuthMiddleware::class];

$router->get('/api/v1', [ApiController::class, 'index']);
$router->post('/api/v1/auth/token', [ApiController::class, 'createToken']);

$router->get('/api/v1/dashboard', [ApiController::class, 'dashboard'], $api);
$router->get('/api/v1/companies', [ApiController::class, 'listCompanies'], $api);
$router->post('/api/v1/companies', [ApiController::class, 'createCompany'], $api);
$router->get('/api/v1/companies/{id}', [ApiController::class, 'getCompany'], $api);
$router->get('/api/v1/suppliers', [ApiController::class, 'listSuppliers'], $api);
$router->post('/api/v1/suppliers', [ApiController::class, 'createSupplier'], $api);
$router->get('/api/v1/purchase-requests', [ApiController::class, 'listPurchaseRequests'], $api);
$router->post('/api/v1/purchase-requests', [ApiController::class, 'createPurchaseRequest'], $api);
$router->get('/api/v1/purchase-orders', [ApiController::class, 'listPurchaseOrders'], $api);
$router->post('/api/v1/purchase-orders', [ApiController::class, 'createPurchaseOrder'], $api);
$router->get('/api/v1/inventory', [ApiController::class, 'listInventory'], $api);
$router->post('/api/v1/inventory', [ApiController::class, 'createInventory'], $api);
