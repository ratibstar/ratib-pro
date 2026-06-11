<?php
declare(strict_types=1);

use Rateb\App\Controllers\Api\ApiController;
use Rateb\App\Core\Middleware\ApiAuthMiddleware;
use Rateb\App\Core\Middleware\ApiModuleMiddleware;

/** @var Rateb\App\Core\Router $router */

$api = [ApiAuthMiddleware::class];

if (!function_exists('rateb_api_mw')) {
    function rateb_api_mw(string $module = ''): array
    {
        $stack = [ApiAuthMiddleware::class];
        if ($module !== '') {
            $stack[] = [ApiModuleMiddleware::class, $module];
        }
        return $stack;
    }
}

$router->get('/api/v1', [ApiController::class, 'index']);
$router->post('/api/v1/auth/token', [ApiController::class, 'createToken']);

$router->get('/api/v1/dashboard', [ApiController::class, 'dashboard'], $api);
$router->get('/api/v1/companies', [ApiController::class, 'listCompanies'], $api);
$router->post('/api/v1/companies', [ApiController::class, 'createCompany'], $api);
$router->get('/api/v1/companies/{id}', [ApiController::class, 'getCompany'], $api);
$router->get('/api/v1/suppliers', [ApiController::class, 'listSuppliers'], rateb_api_mw('suppliers'));
$router->post('/api/v1/suppliers', [ApiController::class, 'createSupplier'], rateb_api_mw('suppliers'));
$router->get('/api/v1/purchase-requests', [ApiController::class, 'listPurchaseRequests'], rateb_api_mw('procurement'));
$router->post('/api/v1/purchase-requests', [ApiController::class, 'createPurchaseRequest'], rateb_api_mw('procurement'));
$router->get('/api/v1/purchase-orders', [ApiController::class, 'listPurchaseOrders'], rateb_api_mw('procurement'));
$router->post('/api/v1/purchase-orders', [ApiController::class, 'createPurchaseOrder'], rateb_api_mw('procurement'));
$router->get('/api/v1/inventory', [ApiController::class, 'listInventory'], rateb_api_mw('inventory'));
$router->post('/api/v1/inventory', [ApiController::class, 'createInventory'], rateb_api_mw('inventory'));
