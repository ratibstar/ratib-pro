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
use Rateb\App\Controllers\Company\ProductCategoriesController;
use Rateb\App\Controllers\Company\StockMovementsController;
use Rateb\App\Controllers\Company\DocumentsController;
use Rateb\App\Controllers\Company\WorkflowsController;
use Rateb\App\Controllers\Company\JournalEntriesController as CompanyJournalEntriesController;
use Rateb\App\Controllers\Company\InventoryBatchesController;
use Rateb\App\Controllers\Company\InventoryAuditsController;
use Rateb\App\Controllers\Company\InventoryCodesController;
use Rateb\App\Controllers\Company\SupplierClassificationsController;
use Rateb\App\Controllers\Company\SupplierKpiController;
use Rateb\App\Controllers\Company\ContractRenewalsController;
use Rateb\App\Controllers\Company\AssetMaintenanceController;
use Rateb\App\Controllers\Company\AssetAssignmentsController;
use Rateb\App\Controllers\Company\AssetDepreciationController;
use Rateb\App\Controllers\Company\DeviceMaintenanceController;
use Rateb\App\Controllers\Company\DeviceSparePartsController;
use Rateb\App\Controllers\Company\DeviceWarrantyController;
use Rateb\App\Controllers\Company\AnalyticsReportsController;

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
$router->get('/company/reports/export', [ReportsController::class, 'export'], rateb_company_mw('reports', 'reports.export'));
$router->get('/company/purchase-requests/export', [PurchaseRequestsController::class, 'export'], rateb_company_mw('procurement', 'reports.export'));
$router->get('/company/purchase-orders/export', [PurchaseOrdersController::class, 'export'], rateb_company_mw('procurement', 'reports.export'));

$router->get('/company/stock-movements', [StockMovementsController::class, 'index'], rateb_company_mw('inventory'));
$router->post('/company/stock-movements', [StockMovementsController::class, 'store'], rateb_company_mw('inventory'));
$router->get('/company/stock-movements/export', [StockMovementsController::class, 'export'], rateb_company_mw('inventory', 'reports.export'));

$router->get('/company/documents', [DocumentsController::class, 'index'], rateb_company_mw('documents'));
$router->post('/company/documents', [DocumentsController::class, 'store'], rateb_company_mw('documents'));

$router->get('/company/workflows', [WorkflowsController::class, 'index'], rateb_company_mw('workflows'));
$router->post('/company/workflows/{id}/approve', [WorkflowsController::class, 'approve'], rateb_company_mw('workflows', 'workflows.approve'));
$router->post('/company/workflows/{id}/reject', [WorkflowsController::class, 'reject'], rateb_company_mw('workflows', 'workflows.approve'));

$router->get('/company/product-categories', [ProductCategoriesController::class, 'index'], rateb_company_mw('inventory'));
$router->get('/company/product-categories/create', [ProductCategoriesController::class, 'create'], rateb_company_mw('inventory'));
$router->post('/company/product-categories', [ProductCategoriesController::class, 'store'], rateb_company_mw('inventory'));
$router->get('/company/product-categories/{id}/edit', [ProductCategoriesController::class, 'edit'], rateb_company_mw('inventory'));
$router->post('/company/product-categories/{id}', [ProductCategoriesController::class, 'update'], rateb_company_mw('inventory'));
$router->post('/company/product-categories/{id}/delete', [ProductCategoriesController::class, 'destroy'], rateb_company_mw('inventory'));

$router->get('/company/notifications', [NotificationsController::class, 'index'], rateb_company_mw());
$router->post('/company/notifications/{id}/read', [NotificationsController::class, 'markRead'], rateb_company_mw());
$router->get('/company/profile', [ProfileController::class, 'index'], rateb_company_mw());
$router->post('/company/profile', [ProfileController::class, 'update'], rateb_company_mw());

$invMw = rateb_company_mw('inventory');
$router->get('/company/inventory-batches', [InventoryBatchesController::class, 'index'], $invMw);
$router->get('/company/inventory-batches/create', [InventoryBatchesController::class, 'create'], $invMw);
$router->post('/company/inventory-batches', [InventoryBatchesController::class, 'store'], $invMw);
$router->get('/company/inventory-batches/export', [InventoryBatchesController::class, 'export'], rateb_company_mw('inventory', 'reports.export'));

$router->get('/company/inventory-audits', [InventoryAuditsController::class, 'index'], $invMw);
$router->get('/company/inventory-audits/create', [InventoryAuditsController::class, 'create'], $invMw);
$router->post('/company/inventory-audits', [InventoryAuditsController::class, 'store'], $invMw);
$router->get('/company/inventory-audits/{id}', [InventoryAuditsController::class, 'show'], $invMw);
$router->post('/company/inventory-audits/{id}/reconcile', [InventoryAuditsController::class, 'reconcile'], $invMw);

$router->get('/company/inventory/{id}/codes', [InventoryCodesController::class, 'show'], $invMw);
$router->post('/company/inventory/{id}/codes/generate', [InventoryCodesController::class, 'generate'], $invMw);

$supMw = rateb_company_mw('suppliers');
$router->get('/company/supplier-classifications', [SupplierClassificationsController::class, 'index'], $supMw);
$router->get('/company/supplier-classifications/create', [SupplierClassificationsController::class, 'create'], $supMw);
$router->post('/company/supplier-classifications', [SupplierClassificationsController::class, 'store'], $supMw);
$router->get('/company/supplier-classifications/{id}/edit', [SupplierClassificationsController::class, 'edit'], $supMw);
$router->post('/company/supplier-classifications/{id}', [SupplierClassificationsController::class, 'update'], $supMw);
$router->post('/company/supplier-classifications/{id}/delete', [SupplierClassificationsController::class, 'destroy'], $supMw);

$router->get('/company/supplier-kpi', [SupplierKpiController::class, 'index'], $supMw);
$router->get('/company/supplier-kpi/export', [SupplierKpiController::class, 'export'], rateb_company_mw('suppliers', 'reports.export'));

$ctrMw = rateb_company_mw('contracts');
$router->get('/company/contract-renewals', [ContractRenewalsController::class, 'index'], $ctrMw);
$router->post('/company/contract-renewals', [ContractRenewalsController::class, 'store'], $ctrMw);

$astMw = rateb_company_mw('assets');
$router->get('/company/asset-maintenance', [AssetMaintenanceController::class, 'index'], $astMw);
$router->post('/company/asset-maintenance', [AssetMaintenanceController::class, 'store'], $astMw);
$router->get('/company/asset-assignments', [AssetAssignmentsController::class, 'index'], $astMw);
$router->post('/company/asset-assignments', [AssetAssignmentsController::class, 'store'], $astMw);
$router->get('/company/asset-depreciation', [AssetDepreciationController::class, 'index'], $astMw);
$router->post('/company/asset-depreciation', [AssetDepreciationController::class, 'store'], $astMw);

$devMw = rateb_company_mw('medical_devices');
$devWriteMw = rateb_company_mw('medical_devices', 'device_service.manage');
$router->get('/company/device-maintenance', [DeviceMaintenanceController::class, 'index'], $devMw);
$router->post('/company/device-maintenance', [DeviceMaintenanceController::class, 'store'], $devWriteMw);
$router->get('/company/device-spare-parts', [DeviceSparePartsController::class, 'index'], $devMw);
$router->post('/company/device-spare-parts', [DeviceSparePartsController::class, 'store'], $devWriteMw);
$router->get('/company/device-warranty', [DeviceWarrantyController::class, 'index'], $devMw);
$router->post('/company/device-warranty/{id}', [DeviceWarrantyController::class, 'update'], $devWriteMw);

$repMw = rateb_company_mw('reports');
$procMw = rateb_company_mw('procurement');
$router->get('/company/reports/procurement', [AnalyticsReportsController::class, 'procurement'], $procMw);
$router->get('/company/reports/procurement/export', [AnalyticsReportsController::class, 'exportProcurement'], rateb_company_mw('reports', 'reports.export'));
$router->get('/company/reports/kpi', [AnalyticsReportsController::class, 'kpi'], $repMw);
$router->get('/company/reports/kpi/export', [AnalyticsReportsController::class, 'exportKpi'], rateb_company_mw('reports', 'reports.export'));
$router->get('/company/reports/cost-analysis', [AnalyticsReportsController::class, 'costAnalysis'], $repMw);
$router->get('/company/reports/cost-analysis/export', [AnalyticsReportsController::class, 'exportCost'], rateb_company_mw('reports', 'reports.export'));
$router->get('/company/reports/supplier-performance', [AnalyticsReportsController::class, 'supplierPerformance'], $supMw);
$router->get('/company/reports/supplier-performance/export', [AnalyticsReportsController::class, 'exportSupplierPerformance'], rateb_company_mw('reports', 'reports.export'));
$router->get('/company/reports/inventory-valuation', [AnalyticsReportsController::class, 'inventoryValuation'], $invMw);
$router->get('/company/reports/inventory-valuation/export', [AnalyticsReportsController::class, 'exportInventoryValuation'], rateb_company_mw('reports', 'reports.export'));
