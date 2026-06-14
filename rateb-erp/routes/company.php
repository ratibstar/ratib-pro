<?php
declare(strict_types=1);

use Rateb\App\Controllers\Company\AssetsController;
use Rateb\App\Controllers\Company\ContractsController;
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
use Rateb\App\Core\Response;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$app = static fn (string $sub): string => '/' . rateb_app_route($sub);

// Legacy /company URLs → unified /admin shell
$router->get('/company/login', static function (): void {
    Response::redirect(rateb_url('login'), 301);
});
$router->post('/company/login', [\Rateb\App\Controllers\Shared\LoginController::class, 'login'], rateb_guest_mw());
$router->get('/company/logout', static function (): void {
    Response::redirect(rateb_url('admin/logout'), 301);
});
$router->get('/company', static function (): void {
    Response::redirect(rateb_url('admin'), 301);
});
$router->get('/company/{legacy:.+}', static function (string $legacy): void {
    Response::redirect(rateb_url(rateb_app_route($legacy)), 301);
});

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
    $mw = rateb_erp_mw($module, '', $path);
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
}

$router->get($app('purchase-orders/{id}'), [PurchaseOrdersController::class, 'show'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->get($app('rfq/{id}/compare'), [RfqController::class, 'compare'], rateb_erp_mw('procurement', '', 'rfq'));
$router->get($app('accounting'), [CompanyAccountingDashboardController::class, 'index'], rateb_erp_mw('accounting', '', 'accounting'));
$router->post($app('accounting/sync'), [CompanyAccountingDashboardController::class, 'sync'], rateb_erp_mw('accounting', 'accounting.post'));
$router->get($app('accounting/accounts-payable'), [CompanyAccountingDashboardController::class, 'accountsPayable'], rateb_erp_mw('accounting', '', 'accounts-payable'));
$router->get($app('accounting/accounts-receivable'), [CompanyAccountingDashboardController::class, 'accountsReceivable'], rateb_erp_mw('accounting', '', 'accounts-receivable'));
$router->get($app('accounting/profit-loss'), [CompanyAccountingDashboardController::class, 'profitLoss'], rateb_erp_mw('accounting', '', 'profit-loss'));
$router->get($app('accounting/balance-sheet'), [CompanyAccountingDashboardController::class, 'balanceSheet'], rateb_erp_mw('accounting', '', 'balance-sheet'));
$router->get($app('chart-of-accounts'), [CompanyChartOfAccountsController::class, 'index'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('chart-of-accounts/create'), [CompanyChartOfAccountsController::class, 'create'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts'), [CompanyChartOfAccountsController::class, 'store'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('chart-of-accounts/{id}/edit'), [CompanyChartOfAccountsController::class, 'edit'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/{id}'), [CompanyChartOfAccountsController::class, 'update'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/{id}/delete'), [CompanyChartOfAccountsController::class, 'destroy'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/bulk-delete'), [CompanyChartOfAccountsController::class, 'bulkDestroy'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('journal-entries'), [CompanyJournalEntriesController::class, 'index'], rateb_erp_mw('accounting', '', 'journal-entries'));
$router->get($app('journal-entries/create'), [CompanyJournalEntriesController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries'), [CompanyJournalEntriesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->get($app('journal-entries/{id}/edit'), [CompanyJournalEntriesController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}/post'), [CompanyJournalEntriesController::class, 'postEntry'], rateb_erp_mw('accounting', 'accounting.post', 'journal-entries'));
$router->post($app('journal-entries/{id}/void'), [CompanyJournalEntriesController::class, 'voidEntry'], rateb_erp_mw('accounting', 'accounting.post', 'journal-entries'));
$router->get($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'show'], rateb_erp_mw('accounting', '', 'journal-entries'));

$router->get($app('reports'), [ReportsController::class, 'index'], rateb_erp_mw('reports', '', 'reports'));
$router->get($app('reports/export'), [ReportsController::class, 'export'], rateb_erp_mw('reports', 'reports.export', 'reports'));
$router->get($app('purchase-requests/export'), [PurchaseRequestsController::class, 'export'], rateb_erp_mw('procurement', 'reports.export', 'purchase-requests'));
$router->get($app('purchase-orders/export'), [PurchaseOrdersController::class, 'export'], rateb_erp_mw('procurement', 'reports.export', 'purchase-orders'));

$router->get($app('stock-movements'), [StockMovementsController::class, 'index'], rateb_erp_mw('inventory', '', 'stock-movements'));
$router->post($app('stock-movements'), [StockMovementsController::class, 'store'], rateb_erp_mw('inventory', '', 'stock-movements'));
$router->get($app('stock-movements/export'), [StockMovementsController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'stock-movements'));

$router->get($app('documents'), [DocumentsController::class, 'index'], rateb_erp_mw('documents', '', 'documents'));
$router->post($app('documents'), [DocumentsController::class, 'store'], rateb_erp_mw('documents', '', 'documents'));

$router->get($app('workflows'), [WorkflowsController::class, 'index'], rateb_erp_mw('workflows', '', 'workflows'));
$router->post($app('workflows/{id}/approve'), [WorkflowsController::class, 'approve'], rateb_erp_mw('workflows', 'workflows.approve'));
$router->post($app('workflows/{id}/reject'), [WorkflowsController::class, 'reject'], rateb_erp_mw('workflows', 'workflows.approve'));

$router->get($app('product-categories'), [ProductCategoriesController::class, 'index'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/create'), [ProductCategoriesController::class, 'create'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories'), [ProductCategoriesController::class, 'store'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/edit'), [ProductCategoriesController::class, 'edit'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}'), [ProductCategoriesController::class, 'update'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}/delete'), [ProductCategoriesController::class, 'destroy'], rateb_erp_mw('inventory', '', 'product-categories'));

$router->get($app('notifications'), [NotificationsController::class, 'index'], rateb_erp_mw('', '', 'notifications'));
$router->post($app('notifications/{id}/read'), [NotificationsController::class, 'markRead'], rateb_erp_mw('', '', 'notifications'));
$router->get($app('profile'), [ProfileController::class, 'index'], rateb_erp_mw());
$router->post($app('profile'), [ProfileController::class, 'update'], rateb_erp_mw());
$router->post($app('profile/regenerate-barcode'), [ProfileController::class, 'regenerateBarcode'], rateb_erp_mw());
$router->get($app('profile/2fa'), [\Rateb\App\Controllers\Shared\TwoFactorController::class, 'setup'], rateb_erp_mw());
$router->post($app('profile/2fa/enable'), [\Rateb\App\Controllers\Shared\TwoFactorController::class, 'enable'], rateb_erp_mw());
$router->post($app('profile/2fa/disable'), [\Rateb\App\Controllers\Shared\TwoFactorController::class, 'disable'], rateb_erp_mw());

$invMw = rateb_erp_mw('inventory', '', 'inventory-batches');
$router->get($app('inventory-batches'), [InventoryBatchesController::class, 'index'], $invMw);
$router->get($app('inventory-batches/create'), [InventoryBatchesController::class, 'create'], $invMw);
$router->post($app('inventory-batches'), [InventoryBatchesController::class, 'store'], $invMw);
$router->get($app('inventory-batches/export'), [InventoryBatchesController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'inventory-batches'));

$wtMw = rateb_erp_mw('inventory', '', 'warehouse-transfers');
$router->get($app('warehouse-transfers'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'index'], $wtMw);
$router->get($app('warehouse-transfers/create'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'create'], $wtMw);
$router->post($app('warehouse-transfers'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'store'], $wtMw);
$router->post($app('warehouse-transfers/{id}/approve'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'approve'], $wtMw);
$router->get($app('inventory-forecast'), [\Rateb\App\Controllers\Company\InventoryForecastController::class, 'index'], rateb_erp_mw('inventory', '', 'inventory-forecast'));

$invAuditMw = rateb_erp_mw('inventory', '', 'inventory-audits');
$router->get($app('inventory-audits'), [InventoryAuditsController::class, 'index'], $invAuditMw);
$router->get($app('inventory-audits/create'), [InventoryAuditsController::class, 'create'], $invAuditMw);
$router->post($app('inventory-audits'), [InventoryAuditsController::class, 'store'], $invAuditMw);
$router->get($app('inventory-audits/{id}'), [InventoryAuditsController::class, 'show'], $invAuditMw);
$router->post($app('inventory-audits/{id}/reconcile'), [InventoryAuditsController::class, 'reconcile'], $invAuditMw);

$invCodesMw = rateb_erp_mw('inventory', '', 'inventory-codes');
$router->get($app('inventory/{id}/codes'), [InventoryCodesController::class, 'show'], $invCodesMw);
$router->post($app('inventory/{id}/codes/generate'), [InventoryCodesController::class, 'generate'], $invCodesMw);

$supMw = rateb_erp_mw('suppliers', '', 'supplier-classifications');
$router->get($app('supplier-classifications'), [SupplierClassificationsController::class, 'index'], $supMw);
$router->get($app('supplier-classifications/create'), [SupplierClassificationsController::class, 'create'], $supMw);
$router->post($app('supplier-classifications'), [SupplierClassificationsController::class, 'store'], $supMw);
$router->get($app('supplier-classifications/{id}/edit'), [SupplierClassificationsController::class, 'edit'], $supMw);
$router->post($app('supplier-classifications/{id}'), [SupplierClassificationsController::class, 'update'], $supMw);
$router->post($app('supplier-classifications/{id}/delete'), [SupplierClassificationsController::class, 'destroy'], $supMw);

$router->get($app('supplier-kpi'), [SupplierKpiController::class, 'index'], rateb_erp_mw('suppliers', '', 'supplier-kpi'));
$router->get($app('supplier-kpi/export'), [SupplierKpiController::class, 'export'], rateb_erp_mw('suppliers', 'reports.export', 'supplier-kpi'));

$ctrMw = rateb_erp_mw('contracts', '', 'contract-renewals');
$router->get($app('contract-renewals'), [ContractRenewalsController::class, 'index'], $ctrMw);
$router->post($app('contract-renewals'), [ContractRenewalsController::class, 'store'], $ctrMw);

$astMw = rateb_erp_mw('assets', '', 'asset-maintenance');
$router->get($app('asset-maintenance'), [AssetMaintenanceController::class, 'index'], $astMw);
$router->post($app('asset-maintenance'), [AssetMaintenanceController::class, 'store'], $astMw);
$router->get($app('asset-assignments'), [AssetAssignmentsController::class, 'index'], rateb_erp_mw('assets', '', 'asset-assignments'));
$router->post($app('asset-assignments'), [AssetAssignmentsController::class, 'store'], rateb_erp_mw('assets', '', 'asset-assignments'));
$router->get($app('asset-depreciation'), [AssetDepreciationController::class, 'index'], rateb_erp_mw('assets', '', 'asset-depreciation'));
$router->post($app('asset-depreciation'), [AssetDepreciationController::class, 'store'], rateb_erp_mw('assets', '', 'asset-depreciation'));

$devMw = rateb_erp_mw('medical_devices', '', 'device-maintenance');
$devWriteMw = rateb_erp_mw('medical_devices', 'device_service.manage', 'device-maintenance');
$router->get($app('device-maintenance'), [DeviceMaintenanceController::class, 'index'], $devMw);
$router->post($app('device-maintenance'), [DeviceMaintenanceController::class, 'store'], $devWriteMw);
$router->get($app('device-spare-parts'), [DeviceSparePartsController::class, 'index'], rateb_erp_mw('medical_devices', '', 'device-spare-parts'));
$router->post($app('device-spare-parts'), [DeviceSparePartsController::class, 'store'], rateb_erp_mw('medical_devices', 'device_spare_parts.manage', 'device-spare-parts'));
$router->get($app('device-warranty'), [DeviceWarrantyController::class, 'index'], rateb_erp_mw('medical_devices', '', 'device-warranty'));
$router->post($app('device-warranty/{id}'), [DeviceWarrantyController::class, 'update'], rateb_erp_mw('medical_devices', 'device_service.manage', 'device-warranty'));

$procMw = rateb_erp_mw('procurement', '', 'reports/procurement');
$repMw = rateb_erp_mw('reports', '', 'reports/kpi');
$router->get($app('reports/procurement'), [AnalyticsReportsController::class, 'procurement'], $procMw);
$router->get($app('reports/procurement/export'), [AnalyticsReportsController::class, 'exportProcurement'], rateb_erp_mw('procurement', 'reports.export', 'reports/procurement'));
$router->get($app('reports/kpi'), [AnalyticsReportsController::class, 'kpi'], $repMw);
$router->get($app('reports/kpi/export'), [AnalyticsReportsController::class, 'exportKpi'], rateb_erp_mw('reports', 'reports.export', 'reports/kpi'));
$router->get($app('reports/cost-analysis'), [AnalyticsReportsController::class, 'costAnalysis'], rateb_erp_mw('reports', '', 'reports/cost-analysis'));
$router->get($app('reports/cost-analysis/export'), [AnalyticsReportsController::class, 'exportCost'], rateb_erp_mw('reports', 'reports.export', 'reports/cost-analysis'));
$router->get($app('reports/supplier-performance'), [AnalyticsReportsController::class, 'supplierPerformance'], rateb_erp_mw('reports', '', 'reports/supplier-performance'));
$router->get($app('reports/supplier-performance/export'), [AnalyticsReportsController::class, 'exportSupplierPerformance'], rateb_erp_mw('reports', 'reports.export', 'reports/supplier-performance'));
$router->get($app('reports/inventory-valuation'), [AnalyticsReportsController::class, 'inventoryValuation'], rateb_erp_mw('inventory', '', 'reports/inventory-valuation'));
$router->get($app('reports/inventory-valuation/export'), [AnalyticsReportsController::class, 'exportInventoryValuation'], rateb_erp_mw('reports', 'reports.export', 'reports/inventory-valuation'));
