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
use Rateb\App\Controllers\Company\SupplierEvaluationsController;
use Rateb\App\Controllers\Company\SuppliersController;
use Rateb\App\Controllers\Company\TendersController;
use Rateb\App\Controllers\Company\WarehousesController;
use Rateb\App\Controllers\Company\AccountingDashboardController as CompanyAccountingDashboardController;
use Rateb\App\Controllers\Company\ChartOfAccountsController as CompanyChartOfAccountsController;
use Rateb\App\Controllers\Company\JournalEntriesController as CompanyJournalEntriesController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$router->get('/company/login', [CompanyAuthController::class, 'showLogin'], rateb_guest_mw());
$router->post('/company/login', [CompanyAuthController::class, 'login'], rateb_guest_mw());
$router->get('/company/logout', [CompanyAuthController::class, 'logout'], rateb_company_mw());

$router->get('/company', [CompanyDashboardController::class, 'index'], rateb_company_mw());

$moduleRoutes = [
    'purchase-requests' => [PurchaseRequestsController::class, 'procurement'],
    'purchase-orders' => [PurchaseOrdersController::class, 'procurement'],
    'rfq' => [RfqController::class, 'procurement'],
    'quotations' => [QuotationsController::class, 'procurement'],
    'suppliers' => [SuppliersController::class, 'suppliers'],
    'supplier-evaluations' => [SupplierEvaluationsController::class, 'suppliers'],
    'inventory' => [InventoryController::class, 'inventory'],
    'warehouses' => [WarehousesController::class, 'inventory'],
    'assets' => [AssetsController::class, 'assets'],
    'medical-devices' => [MedicalDevicesController::class, 'medical_devices'],
    'contracts' => [ContractsController::class, 'contracts'],
    'tenders' => [TendersController::class, 'tenders'],
];

foreach ($moduleRoutes as $path => [$class, $module]) {
    $mw = rateb_company_mw($module);
    $router->get('/company/' . $path, [$class, 'index'], $mw);
    $router->get('/company/' . $path . '/create', [$class, 'create'], $mw);
    $router->post('/company/' . $path, [$class, 'store'], $mw);
    $router->get('/company/' . $path . '/{id}/edit', [$class, 'edit'], $mw);
    $router->post('/company/' . $path . '/{id}', [$class, 'update'], $mw);
    $router->post('/company/' . $path . '/{id}/delete', [$class, 'destroy'], $mw);
    $router->post('/company/' . $path . '/bulk-delete', [$class, 'bulkDestroy'], $mw);
}

$router->get('/company/purchase-orders/{id}', [PurchaseOrdersController::class, 'show'], rateb_company_mw('procurement'));
$router->get('/company/accounting', [CompanyAccountingDashboardController::class, 'index'], rateb_company_mw('accounting'));
$router->post('/company/accounting/sync', [CompanyAccountingDashboardController::class, 'sync'], rateb_company_mw('accounting'));
$router->get('/company/chart-of-accounts', [CompanyChartOfAccountsController::class, 'index'], rateb_company_mw('accounting'));
$router->get('/company/chart-of-accounts/create', [CompanyChartOfAccountsController::class, 'create'], rateb_company_mw('accounting'));
$router->post('/company/chart-of-accounts', [CompanyChartOfAccountsController::class, 'store'], rateb_company_mw('accounting'));
$router->get('/company/chart-of-accounts/{id}/edit', [CompanyChartOfAccountsController::class, 'edit'], rateb_company_mw('accounting'));
$router->post('/company/chart-of-accounts/{id}', [CompanyChartOfAccountsController::class, 'update'], rateb_company_mw('accounting'));
$router->post('/company/chart-of-accounts/{id}/delete', [CompanyChartOfAccountsController::class, 'destroy'], rateb_company_mw('accounting'));
$router->post('/company/chart-of-accounts/bulk-delete', [CompanyChartOfAccountsController::class, 'bulkDestroy'], rateb_company_mw('accounting'));
$router->get('/company/journal-entries', [CompanyJournalEntriesController::class, 'index'], rateb_company_mw('accounting'));
$router->get('/company/journal-entries/{id}', [CompanyJournalEntriesController::class, 'show'], rateb_company_mw('accounting'));

$router->get('/company/reports', [ReportsController::class, 'index'], rateb_company_mw('reports'));
$router->get('/company/notifications', [NotificationsController::class, 'index'], rateb_company_mw());
$router->get('/company/profile', [ProfileController::class, 'index'], rateb_company_mw());
$router->post('/company/profile', [ProfileController::class, 'update'], rateb_company_mw());
