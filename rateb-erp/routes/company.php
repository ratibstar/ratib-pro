<?php
declare(strict_types=1);

use Rateb\App\Controllers\Company\AuthController as CompanyAuthController;
use Rateb\App\Controllers\Company\AssetsController;
use Rateb\App\Controllers\Company\ContractsController;
use Rateb\App\Controllers\Company\DashboardController as CompanyDashboardController;
use Rateb\App\Controllers\Company\InventoryController;
use Rateb\App\Controllers\Company\MedicalDevicesController;
use Rateb\App\Controllers\Company\NotificationsController;
use Rateb\App\Controllers\Company\ProfileController;
use Rateb\App\Controllers\Company\PurchaseOrdersController;
use Rateb\App\Controllers\Company\PurchaseRequestsController;
use Rateb\App\Controllers\Company\QuotationsController;
use Rateb\App\Controllers\Company\ReportsController;
use Rateb\App\Controllers\Company\RfqController;
use Rateb\App\Controllers\Company\SuppliersController;
use Rateb\App\Controllers\Company\TendersController;
use Rateb\App\Controllers\Company\WarehousesController;
use Rateb\App\Core\Middleware\CompanyAuthMiddleware;
use Rateb\App\Core\Middleware\GuestMiddleware;

/** @var Rateb\App\Core\Router $router */

$guest = [GuestMiddleware::class];
$company = [CompanyAuthMiddleware::class];

$router->get('/company/login', [CompanyAuthController::class, 'showLogin'], $guest);
$router->post('/company/login', [CompanyAuthController::class, 'login'], $guest);
$router->get('/company/logout', [CompanyAuthController::class, 'logout'], $company);

$router->get('/company', [CompanyDashboardController::class, 'index'], $company);

foreach ([
    'purchase-requests' => PurchaseRequestsController::class,
    'purchase-orders' => PurchaseOrdersController::class,
    'rfq' => RfqController::class,
    'quotations' => QuotationsController::class,
    'suppliers' => SuppliersController::class,
    'inventory' => InventoryController::class,
    'warehouses' => WarehousesController::class,
    'assets' => AssetsController::class,
    'medical-devices' => MedicalDevicesController::class,
    'contracts' => ContractsController::class,
    'tenders' => TendersController::class,
] as $path => $class) {
    $router->get('/company/' . $path, [$class, 'index'], $company);
    $router->get('/company/' . $path . '/create', [$class, 'create'], $company);
    $router->post('/company/' . $path, [$class, 'store'], $company);
    $router->get('/company/' . $path . '/{id}/edit', [$class, 'edit'], $company);
    $router->post('/company/' . $path . '/{id}', [$class, 'update'], $company);
    $router->post('/company/' . $path . '/{id}/delete', [$class, 'destroy'], $company);
}

$router->get('/company/purchase-orders/{id}', [PurchaseOrdersController::class, 'show'], $company);
$router->get('/company/reports', [ReportsController::class, 'index'], $company);
$router->get('/company/notifications', [NotificationsController::class, 'index'], $company);
$router->get('/company/profile', [ProfileController::class, 'index'], $company);
$router->post('/company/profile', [ProfileController::class, 'update'], $company);
