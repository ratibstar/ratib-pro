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
use Rateb\App\Controllers\Company\BranchesController;
use Rateb\App\Controllers\Company\AccountingDashboardController as CompanyAccountingDashboardController;
use Rateb\App\Controllers\Company\HrDashboardController;
use Rateb\App\Controllers\Company\HrEmployeesController;
use Rateb\App\Controllers\Company\HrDepartmentsController;
use Rateb\App\Controllers\Company\HrAttendanceController;
use Rateb\App\Controllers\Company\HrLeavesController;
use Rateb\App\Controllers\Company\HrLeaveTypesController;
use Rateb\App\Controllers\Company\HrPayrollController;
use Rateb\App\Controllers\Company\HrReportsController;
use Rateb\App\Controllers\Company\HrHolidaysController;
use Rateb\App\Controllers\Company\HrWorkplacesController;
use Rateb\App\Controllers\Company\HrPermissionRequestsController;
use Rateb\App\Controllers\Company\HrLoanTypesController;
use Rateb\App\Controllers\Company\HrLoansController;
use Rateb\App\Controllers\Company\HrPayrollComponentsController;
use Rateb\App\Controllers\Company\HrPayrollStructuresController;
use Rateb\App\Controllers\Company\HrEmployeeDocumentsController;
use Rateb\App\Controllers\Company\HrFleetController;
use Rateb\App\Controllers\Company\HrEmployeeRequestsController;
use Rateb\App\Controllers\Company\HrAttendanceBulkController;
use Rateb\App\Controllers\Company\ChartOfAccountsController as CompanyChartOfAccountsController;
use Rateb\App\Controllers\Company\ProductCategoriesController;
use Rateb\App\Controllers\Company\StockMovementsController;
use Rateb\App\Controllers\Company\DocumentsController;
use Rateb\App\Controllers\Company\WorkflowsController;
use Rateb\App\Controllers\Company\JournalEntriesController as CompanyJournalEntriesController;
use Rateb\App\Controllers\Company\CashVouchersController as CompanyCashVouchersController;
use Rateb\App\Controllers\Company\FiscalPeriodsController as CompanyFiscalPeriodsController;
use Rateb\App\Controllers\Company\CostCentersController as CompanyCostCentersController;
use Rateb\App\Controllers\Company\CustomersController as CompanyCustomersController;
use Rateb\App\Controllers\Company\BankAccountsController as CompanyBankAccountsController;
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

$redirectApprovalsOversight = static function (): void {
    if (rateb_is_super_admin()) {
        Response::redirect(rateb_url('admin/oversight/approvals'), 302);
    }
    \Rateb\App\Core\SessionManager::flash('error', __('approvals_admin_only'));
    Response::redirect(rateb_url('admin'));
};
$blockCompanyApprovalAction = static function (): void {
    \Rateb\App\Core\SessionManager::flash('error', __('approvals_admin_only'));
    Response::redirect(rateb_is_super_admin() ? rateb_url('admin/oversight/approvals') : rateb_url('admin'), 302);
};

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
$router->get('/company/{legacy:.+}', static function (array $params): void {
    $legacy = (string) ($params['legacy'] ?? '');
    Response::redirect(rateb_url(rateb_app_route($legacy)), 301);
});

// Legacy /accounting URLs (without admin/ops prefix) → unified ops shell
$router->get('/accounting', static function (): void {
    $target = rateb_url(rateb_app_route('accounting'));
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '') {
        $target .= (strpos($target, '?') === false ? '?' : '&') . $qs;
    }
    Response::redirect($target, 301);
});
$router->get('/accounting/{legacy:.+}', static function (array $params): void {
    $legacy = (string) ($params['legacy'] ?? '');
    $target = rateb_url(rateb_app_route('accounting/' . $legacy));
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '') {
        $target .= (strpos($target, '?') === false ? '?' : '&') . $qs;
    }
    Response::redirect($target, 301);
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
    'branches' => [BranchesController::class, ''],
    'assets' => [AssetsController::class, 'assets'],
    'medical-devices' => [MedicalDevicesController::class, 'medical_devices'],
    'contracts' => [ContractsController::class, 'contracts'],
    'tenders' => [TendersController::class, 'tenders'],
];

$branchesMw = rateb_erp_mw('', '', 'branches');
$router->get($app('branches/setup-check'), [BranchesController::class, 'setupCheck'], $branchesMw);
$router->post($app('branches/{id}/toggle-status'), [BranchesController::class, 'toggleStatus'], $branchesMw);
$router->get($app('branches/export'), [BranchesController::class, 'export'], rateb_erp_mw('', 'reports.export', 'branches'));

foreach ($moduleRoutes as $path => [$class, $module]) {
    $mw = rateb_erp_mw($module, '', $path);
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
    $router->get($app($path . '/{id}/documents/panel'), [$class, 'documentsPanel'], $mw);
    $router->get($app($path . '/{id}/documents'), [$class, 'documents'], $mw);
    $router->post($app($path . '/{id}/documents'), [$class, 'storeDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}'), [$class, 'updateDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}/delete'), [$class, 'destroyDocument'], $mw);
}

$seMw = rateb_erp_mw('suppliers', '', 'supplier-evaluations');
$router->get($app('supplier-evaluations/approvals'), $redirectApprovalsOversight, $seMw);
$router->get($app('supplier-evaluations/history'), [SupplierEvaluationsController::class, 'supplierHistory'], $seMw);
$router->post($app('supplier-evaluations/{id}/approve'), $blockCompanyApprovalAction, $seMw);
$router->post($app('supplier-evaluations/{id}/reject'), $blockCompanyApprovalAction, $seMw);

$router->get($app('inventory/warehouse-items'), [InventoryController::class, 'warehouseItemsJson'], rateb_erp_mw('inventory', '', 'inventory'));

$router->get($app('purchase-requests/export'), [PurchaseRequestsController::class, 'export'], rateb_erp_mw('procurement', 'reports.export', 'purchase-requests'));
$router->get($app('purchase-requests/line-attachment/{itemId}'), [PurchaseRequestsController::class, 'downloadLineAttachment'], rateb_erp_mw('procurement', '', 'purchase-requests'));
$router->get($app('purchase-orders/export'), [PurchaseOrdersController::class, 'export'], rateb_erp_mw('procurement', 'reports.export', 'purchase-orders'));
$router->get($app('customs-clearance-costs/export'), [PurchaseOrdersController::class, 'customsExport'], rateb_erp_mw('accounting', 'reports.export', 'customs-clearance-costs'));
$router->get($app('customs-clearance-costs'), [PurchaseOrdersController::class, 'customsIndex'], rateb_erp_mw('accounting', '', 'customs-clearance-costs'));
$router->get($app('customs-clearance-costs/{id}/edit'), [PurchaseOrdersController::class, 'customsEdit'], rateb_erp_mw('accounting', 'customs_clearance.manage', 'customs-clearance-costs'));
$router->post($app('customs-clearance-costs/{id}'), [PurchaseOrdersController::class, 'customsUpdate'], rateb_erp_mw('accounting', 'customs_clearance.manage', 'customs-clearance-costs'));

$router->get($app('purchase-requests/{id}'), [PurchaseRequestsController::class, 'show'], rateb_erp_mw('procurement', '', 'purchase-requests'));
$router->post($app('purchase-requests/{id}/convert-to-po'), [PurchaseRequestsController::class, 'convertToPo'], rateb_erp_mw('procurement', '', 'purchase-requests'));
$router->post($app('purchase-requests/{id}/submit'), [PurchaseRequestsController::class, 'submit'], rateb_erp_mw('procurement', '', 'purchase-requests'));
$router->get($app('purchase-orders/{id}'), [PurchaseOrdersController::class, 'show'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->get($app('purchase-orders/{id}/invoice'), [PurchaseOrdersController::class, 'invoiceForm'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->post($app('purchase-orders/{id}/invoice'), [PurchaseOrdersController::class, 'saveInvoice'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->get($app('purchase-orders/{id}/print'), [PurchaseOrdersController::class, 'print'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->post($app('purchase-orders/{id}/receive'), [PurchaseOrdersController::class, 'receive'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->post($app('purchase-orders/{id}/submit'), [PurchaseOrdersController::class, 'submit'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->post($app('quotations/{id}/create-po'), [PurchaseOrdersController::class, 'createFromQuotation'], rateb_erp_mw('procurement', '', 'purchase-orders'));
$router->get($app('rfq/{id}/compare'), [RfqController::class, 'compare'], rateb_erp_mw('procurement', '', 'rfq'));
$router->get($app('hr'), [HrDashboardController::class, 'index'], rateb_erp_mw('hr', '', 'hr'));

$hrAttMw = rateb_erp_mw('hr', '', 'hr-attendance');
$router->get($app('hr/attendance/bulk'), [HrAttendanceBulkController::class, 'index'], $hrAttMw);
$router->post($app('hr/attendance/bulk'), [HrAttendanceBulkController::class, 'store'], $hrAttMw);

$hrCrudRoutes = [
    'hr/employees' => ['class' => HrEmployeesController::class, 'entity' => 'hr-employees'],
    'hr/departments' => ['class' => HrDepartmentsController::class, 'entity' => 'hr-employees'],
    'hr/holidays' => ['class' => HrHolidaysController::class, 'entity' => 'hr-leaves'],
    'hr/workplaces' => ['class' => HrWorkplacesController::class, 'entity' => 'hr-attendance'],
    'hr/permission-requests' => ['class' => HrPermissionRequestsController::class, 'entity' => 'hr-attendance'],
    'hr/attendance' => ['class' => HrAttendanceController::class, 'entity' => 'hr-attendance'],
    'hr/leaves' => ['class' => HrLeavesController::class, 'entity' => 'hr-leaves'],
    'hr/leave-types' => ['class' => HrLeaveTypesController::class, 'entity' => 'hr-leaves'],
    'hr/loans' => ['class' => HrLoansController::class, 'entity' => 'hr-payroll'],
    'hr/loan-types' => ['class' => HrLoanTypesController::class, 'entity' => 'hr-payroll'],
    'hr/documents' => ['class' => HrEmployeeDocumentsController::class, 'entity' => 'hr-employees'],
    'hr/fleet' => ['class' => HrFleetController::class, 'entity' => 'hr-employees'],
    'hr/requests' => ['class' => HrEmployeeRequestsController::class, 'entity' => 'hr-leaves'],
];

$hrLeaveMw = rateb_erp_mw('hr', '', 'hr-leaves');
$router->get($app('hr/leaves/balances'), [HrLeavesController::class, 'balances'], $hrLeaveMw);

foreach ($hrCrudRoutes as $path => $cfg) {
    $class = $cfg['class'];
    $mw = rateb_erp_mw('hr', '', $cfg['entity']);
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    if ($path === 'hr/employees') {
        $router->get($app('hr/employees/export'), [HrEmployeesController::class, 'export'], $mw);
        $router->get($app('hr/employees/{id}'), [HrEmployeesController::class, 'show'], $mw);
    }
    if ($path === 'hr/fleet') {
        $router->get($app('hr/fleet/{id}'), [HrFleetController::class, 'show'], $mw);
        $router->get($app('hr/fleet/{id}/print'), [HrFleetController::class, 'print'], $mw);
        $router->get($app('hr/fleet/{id}/receipt'), [HrFleetController::class, 'employeeReceipt'], $mw);
    }
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
    $router->get($app($path . '/{id}/documents/panel'), [$class, 'documentsPanel'], $mw);
    $router->get($app($path . '/{id}/documents'), [$class, 'documents'], $mw);
    $router->post($app($path . '/{id}/documents'), [$class, 'storeDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}'), [$class, 'updateDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}/delete'), [$class, 'destroyDocument'], $mw);
}

$hrLeaveMw = rateb_erp_mw('hr', '', 'hr-leaves');
$router->post($app('hr/leaves/{id}/approve'), [HrLeavesController::class, 'approve'], $hrLeaveMw);
$router->post($app('hr/leaves/{id}/reject'), [HrLeavesController::class, 'reject'], $hrLeaveMw);
$router->post($app('hr/permission-requests/{id}/approve'), [HrPermissionRequestsController::class, 'approve'], $hrAttMw);
$router->post($app('hr/permission-requests/{id}/reject'), [HrPermissionRequestsController::class, 'reject'], $hrAttMw);
$router->post($app('hr/requests/{id}/approve'), [HrEmployeeRequestsController::class, 'approve'], $hrLeaveMw);
$router->post($app('hr/requests/{id}/reject'), [HrEmployeeRequestsController::class, 'reject'], $hrLeaveMw);

$hrPayMw = rateb_erp_mw('hr', '', 'hr-payroll');
$hrPayrollSubRoutes = [
    'hr/payroll/components' => HrPayrollComponentsController::class,
    'hr/payroll/structure' => HrPayrollStructuresController::class,
];
foreach ($hrPayrollSubRoutes as $path => $class) {
    $router->get($app($path), [$class, 'index'], $hrPayMw);
    $router->get($app($path . '/create'), [$class, 'create'], $hrPayMw);
    $router->post($app($path), [$class, 'store'], $hrPayMw);
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $hrPayMw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $hrPayMw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $hrPayMw);
}
$router->get($app('hr/payroll'), [HrPayrollController::class, 'index'], $hrPayMw);
$router->get($app('hr/payroll/create'), [HrPayrollController::class, 'create'], $hrPayMw);
$router->post($app('hr/payroll'), [HrPayrollController::class, 'store'], $hrPayMw);
$router->get($app('hr/payroll/{id}'), [HrPayrollController::class, 'show'], $hrPayMw);
$router->get($app('hr/payroll/{id}/edit'), [HrPayrollController::class, 'edit'], $hrPayMw);
$router->post($app('hr/payroll/{id}'), [HrPayrollController::class, 'update'], $hrPayMw);
$router->post($app('hr/payroll/{id}/delete'), [HrPayrollController::class, 'destroy'], $hrPayMw);
$router->post($app('hr/payroll/{id}/generate'), [HrPayrollController::class, 'generate'], $hrPayMw);
$router->post($app('hr/payroll/{id}/approve'), [HrPayrollController::class, 'approve'], $hrPayMw);
$router->post($app('hr/payroll/{id}/post'), [HrPayrollController::class, 'post'], $hrPayMw);
$router->get($app('hr/payroll/{id}/export'), [HrPayrollController::class, 'export'], $hrPayMw);
$router->get($app('hr/payroll/{id}/payslip/{lineId}'), [HrPayrollController::class, 'payslip'], $hrPayMw);

$hrReportsMw = rateb_erp_mw('hr', '', 'hr');
$router->get($app('hr/reports/leaves'), [HrReportsController::class, 'leaves'], $hrReportsMw);
$router->get($app('hr/reports/leaves/export'), [HrReportsController::class, 'leavesExport'], rateb_erp_mw('hr', 'reports.export', 'hr'));
$router->get($app('hr/reports'), [HrReportsController::class, 'index'], $hrReportsMw);
$router->get($app('hr/reports/export'), [HrReportsController::class, 'export'], rateb_erp_mw('hr', 'reports.export', 'hr'));
$router->get($app('accounting'), [CompanyAccountingDashboardController::class, 'index'], rateb_erp_mw('accounting', '', 'accounting'));
$router->get($app('accounting/reports'), [CompanyAccountingDashboardController::class, 'reportsHub'], rateb_erp_mw('accounting', '', 'accounting-reports'));
$router->get($app('accounting/trial-balance'), [CompanyAccountingDashboardController::class, 'trialBalanceReport'], rateb_erp_mw('accounting', '', 'trial-balance'));
$router->get($app('accounting/journal-register'), [CompanyAccountingDashboardController::class, 'journalRegister'], rateb_erp_mw('accounting', '', 'journal-register'));
$router->get($app('accounting/account-statement'), [CompanyAccountingDashboardController::class, 'accountStatement'], rateb_erp_mw('accounting', '', 'account-statement'));
$router->get($app('accounting/partners-subsidiary-ledger'), [CompanyAccountingDashboardController::class, 'partnersSubsidiaryLedger'], rateb_erp_mw('accounting', '', 'partners-subsidiary-ledger'));
$router->post($app('accounting/sync'), [CompanyAccountingDashboardController::class, 'sync'], rateb_erp_mw('accounting', 'accounting.post'));
$router->get($app('accounting/accounts-payable'), [CompanyAccountingDashboardController::class, 'accountsPayable'], rateb_erp_mw('accounting', '', 'accounts-payable'));
$router->get($app('accounting/accounts-receivable'), [CompanyAccountingDashboardController::class, 'accountsReceivable'], rateb_erp_mw('accounting', '', 'accounts-receivable'));
$router->get($app('accounting/profit-loss'), [CompanyAccountingDashboardController::class, 'profitLoss'], rateb_erp_mw('accounting', '', 'profit-loss'));
$router->get($app('accounting/cost-of-sales'), [CompanyAccountingDashboardController::class, 'costOfSales'], rateb_erp_mw('accounting', '', 'cost-of-sales'));
$router->get($app('accounting/balance-sheet'), [CompanyAccountingDashboardController::class, 'balanceSheet'], rateb_erp_mw('accounting', '', 'balance-sheet'));
$router->get($app('accounting/vat-report'), [CompanyAccountingDashboardController::class, 'vatReport'], rateb_erp_mw('accounting', '', 'vat-report'));
$router->get($app('accounting/cost-center-report'), [CompanyAccountingDashboardController::class, 'costCenterReport'], rateb_erp_mw('accounting', '', 'cost-center-report'));
$router->get($app('accounting/zatca-settings'), [CompanyAccountingDashboardController::class, 'zatcaSettings'], rateb_erp_mw('accounting', '', 'zatca-settings'));
$router->post($app('accounting/zatca-settings'), [CompanyAccountingDashboardController::class, 'saveZatcaSettings'], rateb_erp_mw('accounting', 'accounting.manage', 'zatca-settings'));
$router->post($app('accounting/zatca-qr/{id}'), [CompanyAccountingDashboardController::class, 'generateZatcaQr'], rateb_erp_mw('accounting', 'accounting.manage', 'zatca-settings'));
$router->get($app('accounting/budget-report'), [CompanyAccountingDashboardController::class, 'budgetReport'], rateb_erp_mw('accounting', '', 'budget-report'));
$router->post($app('accounting/budget-report'), [CompanyAccountingDashboardController::class, 'saveBudget'], rateb_erp_mw('accounting', 'accounting.manage', 'budget-report'));
$router->get($app('accounting/cfo-dashboard'), [CompanyAccountingDashboardController::class, 'cfoDashboard'], rateb_erp_mw('accounting', '', 'cfo-dashboard'));
$router->get($app('accounting/bank-reconciliation'), [CompanyAccountingDashboardController::class, 'bankReconciliation'], rateb_erp_mw('accounting', '', 'bank-reconciliation'));
$router->get($app('accounting/export/trial-balance'), [CompanyAccountingDashboardController::class, 'exportTrialBalance'], rateb_erp_mw('accounting', 'reports.export', 'accounting'));
$router->get($app('accounting/export/journals'), [CompanyAccountingDashboardController::class, 'exportJournals'], rateb_erp_mw('accounting', 'reports.export', 'accounting'));
$router->get($app('accounting/export/profit-loss'), [CompanyAccountingDashboardController::class, 'exportProfitLoss'], rateb_erp_mw('accounting', 'reports.export', 'accounting'));
$router->get($app('accounting/export/balance-sheet'), [CompanyAccountingDashboardController::class, 'exportBalanceSheet'], rateb_erp_mw('accounting', 'reports.export', 'accounting'));
$router->get($app('accounting/export/vat-report'), [CompanyAccountingDashboardController::class, 'exportVatReport'], rateb_erp_mw('accounting', 'reports.export', 'accounting'));
$router->get($app('accounting/supplier-payments/create'), [CompanyAccountingDashboardController::class, 'supplierPaymentForm'], rateb_erp_mw('accounting', 'accounting.post', 'accounts-payable'));
$router->post($app('accounting/supplier-payments'), [CompanyAccountingDashboardController::class, 'storeSupplierPayment'], rateb_erp_mw('accounting', 'accounting.post', 'accounts-payable'));
$router->get($app('accounting/entry-approval'), $redirectApprovalsOversight, rateb_erp_mw('accounting', '', 'entry-approval'));
$router->get($app('accounting/supplier-payments'), [CompanyAccountingDashboardController::class, 'supplierPayments'], rateb_erp_mw('accounting', '', 'supplier-payments'));
$router->get($app('accounting/supplier-payments/export'), [CompanyAccountingDashboardController::class, 'exportSupplierPayments'], rateb_erp_mw('accounting', 'reports.export', 'supplier-payments'));
$router->post($app('accounting/supplier-payments/{id}/void'), [CompanyAccountingDashboardController::class, 'voidSupplierPayment'], rateb_erp_mw('accounting', 'accounting.post', 'supplier-payments'));
$router->post($app('accounting/supplier-payments/bulk-void'), [CompanyAccountingDashboardController::class, 'bulkVoidSupplierPayments'], rateb_erp_mw('accounting', 'accounting.post', 'supplier-payments'));
$router->get($app('accounting/bank-reconciliation/{id}'), [CompanyAccountingDashboardController::class, 'bankReconciliationDetail'], rateb_erp_mw('accounting', '', 'bank-reconciliation'));
$router->post($app('accounting/bank-reconciliation/{id}/import'), [CompanyAccountingDashboardController::class, 'importBankStatement'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-reconciliation'));
$router->post($app('accounting/bank-reconciliation/lines/{line_id}/reconcile'), [CompanyAccountingDashboardController::class, 'reconcileStatementLine'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-reconciliation'));
$router->post($app('accounting/bank-reconciliation/lines/{line_id}/delete'), [CompanyAccountingDashboardController::class, 'destroyBankStatementLine'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-reconciliation'));
$router->post($app('accounting/bank-reconciliation/bulk-delete-lines'), [CompanyAccountingDashboardController::class, 'bulkDestroyBankStatementLines'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-reconciliation'));
$router->get($app('accounting/coa-tree'), [CompanyChartOfAccountsController::class, 'coaTree'], rateb_erp_mw('accounting', '', 'coa-tree'));
$router->get($app('chart-of-accounts'), [CompanyChartOfAccountsController::class, 'index'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('chart-of-accounts/create'), [CompanyChartOfAccountsController::class, 'create'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts'), [CompanyChartOfAccountsController::class, 'store'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('chart-of-accounts/{id}'), [CompanyChartOfAccountsController::class, 'show'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('chart-of-accounts/{id}/edit'), [CompanyChartOfAccountsController::class, 'edit'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/bulk-delete'), [CompanyChartOfAccountsController::class, 'bulkDestroy'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/{id}'), [CompanyChartOfAccountsController::class, 'update'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->post($app('chart-of-accounts/{id}/delete'), [CompanyChartOfAccountsController::class, 'destroy'], rateb_erp_mw('accounting', '', 'chart-of-accounts'));
$router->get($app('journal-entries'), [CompanyJournalEntriesController::class, 'index'], rateb_erp_mw('accounting', '', 'journal-entries'));
$router->get($app('journal-entries/create'), [CompanyJournalEntriesController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries'), [CompanyJournalEntriesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/bulk-approve'), [CompanyJournalEntriesController::class, 'bulkApprove'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-reject'), [CompanyJournalEntriesController::class, 'bulkReject'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-void'), [CompanyJournalEntriesController::class, 'bulkVoid'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-delete'), [CompanyJournalEntriesController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->get($app('journal-entries/{id}/edit'), [CompanyJournalEntriesController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}/submit-approval'), [CompanyJournalEntriesController::class, 'submitForApproval'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}/post'), [CompanyJournalEntriesController::class, 'postEntry'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/reject'), [CompanyJournalEntriesController::class, 'rejectEntry'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/void'), [CompanyJournalEntriesController::class, 'voidEntry'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/delete'), [CompanyJournalEntriesController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->get($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'show'], rateb_erp_mw('accounting', '', 'journal-entries'));

$router->get($app('accounting/voucher-approval'), $redirectApprovalsOversight, rateb_erp_mw('accounting', '', 'voucher-approval'));
$router->get($app('cash-vouchers'), [CompanyCashVouchersController::class, 'index'], rateb_erp_mw('accounting', '', 'cash-vouchers'));
$router->get($app('cash-vouchers/create'), [CompanyCashVouchersController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers'), [CompanyCashVouchersController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-approve'), [CompanyCashVouchersController::class, 'bulkApprove'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-reject'), [CompanyCashVouchersController::class, 'bulkReject'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-void'), [CompanyCashVouchersController::class, 'bulkVoid'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-delete'), [CompanyCashVouchersController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->get($app('cash-vouchers/{id}/edit'), [CompanyCashVouchersController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}'), [CompanyCashVouchersController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->get($app('cash-vouchers/{id}'), [CompanyCashVouchersController::class, 'show'], rateb_erp_mw('accounting', '', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/submit-approval'), [CompanyCashVouchersController::class, 'submitForApproval'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/post'), [CompanyCashVouchersController::class, 'postVoucher'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/reject'), [CompanyCashVouchersController::class, 'rejectVoucher'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/void'), [CompanyCashVouchersController::class, 'voidVoucher'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/delete'), [CompanyCashVouchersController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));

$router->get($app('fiscal-periods'), [CompanyFiscalPeriodsController::class, 'index'], rateb_erp_mw('accounting', '', 'fiscal-periods'));
$router->get($app('fiscal-periods/create'), [CompanyFiscalPeriodsController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'fiscal-periods'));
$router->post($app('fiscal-periods'), [CompanyFiscalPeriodsController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'fiscal-periods'));
$router->get($app('fiscal-periods/{id}'), [CompanyFiscalPeriodsController::class, 'show'], rateb_erp_mw('accounting', '', 'fiscal-periods'));
$router->post($app('fiscal-periods/{id}/delete'), [CompanyFiscalPeriodsController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'fiscal-periods'));
$router->post($app('fiscal-periods/{id}/close'), [CompanyFiscalPeriodsController::class, 'close'], rateb_erp_mw('accounting', 'accounting.post', 'fiscal-periods'));
$router->post($app('fiscal-periods/{id}/reopen'), [CompanyFiscalPeriodsController::class, 'reopen'], rateb_erp_mw('accounting', 'accounting.post', 'fiscal-periods'));

$router->get($app('bank-accounts'), [CompanyBankAccountsController::class, 'index'], rateb_erp_mw('accounting', '', 'bank-accounts'));
$router->get($app('bank-accounts/create'), [CompanyBankAccountsController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));
$router->post($app('bank-accounts'), [CompanyBankAccountsController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));
$router->get($app('bank-accounts/{id}'), [CompanyBankAccountsController::class, 'show'], rateb_erp_mw('accounting', '', 'bank-accounts'));
$router->get($app('bank-accounts/{id}/edit'), [CompanyBankAccountsController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));
$router->post($app('bank-accounts/bulk-delete'), [CompanyBankAccountsController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));
$router->post($app('bank-accounts/{id}'), [CompanyBankAccountsController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));
$router->post($app('bank-accounts/{id}/delete'), [CompanyBankAccountsController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'bank-accounts'));

$router->get($app('cost-centers'), [CompanyCostCentersController::class, 'index'], rateb_erp_mw('accounting', '', 'cost-centers'));
$router->get($app('cost-centers/create'), [CompanyCostCentersController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));
$router->post($app('cost-centers'), [CompanyCostCentersController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));
$router->get($app('cost-centers/{id}'), [CompanyCostCentersController::class, 'show'], rateb_erp_mw('accounting', '', 'cost-centers'));
$router->get($app('cost-centers/{id}/edit'), [CompanyCostCentersController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));
$router->post($app('cost-centers/{id}'), [CompanyCostCentersController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));
$router->post($app('cost-centers/{id}/delete'), [CompanyCostCentersController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));
$router->post($app('cost-centers/bulk-delete'), [CompanyCostCentersController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'cost-centers'));

$router->get($app('customers'), [CompanyCustomersController::class, 'index'], rateb_erp_mw('accounting', '', 'customers'));
$router->get($app('customers/create'), [CompanyCustomersController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));
$router->post($app('customers'), [CompanyCustomersController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));
$router->get($app('customers/{id}'), [CompanyCustomersController::class, 'show'], rateb_erp_mw('accounting', '', 'customers'));
$router->get($app('customers/{id}/edit'), [CompanyCustomersController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));
$router->post($app('customers/{id}'), [CompanyCustomersController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));
$router->post($app('customers/{id}/delete'), [CompanyCustomersController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));
$router->post($app('customers/bulk-delete'), [CompanyCustomersController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'customers'));

$router->get($app('reports'), [ReportsController::class, 'index'], rateb_erp_mw('reports', '', 'reports'));
$router->get($app('reports/export'), [ReportsController::class, 'export'], rateb_erp_mw('reports', 'reports.export', 'reports'));
$router->get($app('stock-movements'), [StockMovementsController::class, 'index'], rateb_erp_mw('inventory', '', 'stock-movements'));
$router->post($app('stock-movements'), [StockMovementsController::class, 'store'], rateb_erp_mw('inventory', '', 'stock-movements'));
$router->post($app('stock-movements/bulk-delete'), [StockMovementsController::class, 'bulkDestroy'], rateb_erp_mw('inventory', '', 'stock-movements'));
$router->get($app('stock-movements/export'), [StockMovementsController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'stock-movements'));

$router->get($app('documents'), [DocumentsController::class, 'index'], rateb_erp_mw('documents', '', 'documents'));
$router->post($app('documents'), [DocumentsController::class, 'store'], rateb_erp_mw('documents', '', 'documents'));

$router->get($app('workflows'), $redirectApprovalsOversight, rateb_erp_mw('workflows', '', 'workflows'));
$router->post($app('workflows/{id}/approve'), $blockCompanyApprovalAction, rateb_erp_mw('workflows', 'workflows.approve'));
$router->post($app('workflows/{id}/reject'), $blockCompanyApprovalAction, rateb_erp_mw('workflows', 'workflows.approve'));

$router->get($app('product-categories'), [ProductCategoriesController::class, 'index'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/create'), [ProductCategoriesController::class, 'create'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories'), [ProductCategoriesController::class, 'store'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/edit'), [ProductCategoriesController::class, 'edit'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}'), [ProductCategoriesController::class, 'update'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}/delete'), [ProductCategoriesController::class, 'destroy'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/bulk-delete'), [ProductCategoriesController::class, 'bulkDestroy'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/documents/panel'), [ProductCategoriesController::class, 'documentsPanel'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/documents'), [ProductCategoriesController::class, 'documents'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}/documents'), [ProductCategoriesController::class, 'storeDocument'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}/documents/{docId}'), [ProductCategoriesController::class, 'updateDocument'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->post($app('product-categories/{id}/documents/{docId}/delete'), [ProductCategoriesController::class, 'destroyDocument'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/image'), [ProductCategoriesController::class, 'image'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/{id}/copy'), [ProductCategoriesController::class, 'copy'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/report'), [ProductCategoriesController::class, 'report'], rateb_erp_mw('inventory', '', 'product-categories'));
$router->get($app('product-categories/export'), [ProductCategoriesController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'product-categories'));

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
$router->get($app('inventory-batches/{id}/edit'), [InventoryBatchesController::class, 'edit'], $invMw);
$router->post($app('inventory-batches/{id}'), [InventoryBatchesController::class, 'update'], $invMw);
$router->post($app('inventory-batches/{id}/delete'), [InventoryBatchesController::class, 'destroy'], $invMw);
$router->post($app('inventory-batches/bulk-delete'), [InventoryBatchesController::class, 'bulkDestroy'], $invMw);
$router->get($app('inventory-batches/{id}/documents/panel'), [InventoryBatchesController::class, 'documentsPanel'], $invMw);
$router->get($app('inventory-batches/{id}/documents'), [InventoryBatchesController::class, 'documents'], $invMw);
$router->post($app('inventory-batches/{id}/documents'), [InventoryBatchesController::class, 'storeDocument'], $invMw);
$router->post($app('inventory-batches/{id}/documents/{docId}'), [InventoryBatchesController::class, 'updateDocument'], $invMw);
$router->post($app('inventory-batches/{id}/documents/{docId}/delete'), [InventoryBatchesController::class, 'destroyDocument'], $invMw);
$router->get($app('inventory-batches/export'), [InventoryBatchesController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'inventory-batches'));

$wtMw = rateb_erp_mw('inventory', '', 'warehouse-transfers');
$router->get($app('warehouse-transfers'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'index'], $wtMw);
$router->get($app('warehouse-transfers/create'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'create'], $wtMw);
$router->post($app('warehouse-transfers'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'store'], $wtMw);
$router->post($app('warehouse-transfers/{id}/approve'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'approve'], $wtMw);
$router->get($app('warehouse-transfers/export'), [\Rateb\App\Controllers\Company\WarehouseTransfersController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'warehouse-transfers'));
$router->get($app('inventory-forecast'), [\Rateb\App\Controllers\Company\InventoryForecastController::class, 'index'], rateb_erp_mw('inventory', '', 'inventory-forecast'));

$invAuditMw = rateb_erp_mw('inventory', '', 'inventory-audits');
$router->get($app('inventory-audits'), [InventoryAuditsController::class, 'index'], $invAuditMw);
$router->get($app('inventory-audits/create'), [InventoryAuditsController::class, 'create'], $invAuditMw);
$router->post($app('inventory-audits'), [InventoryAuditsController::class, 'store'], $invAuditMw);
$router->get($app('inventory-audits/export'), [InventoryAuditsController::class, 'export'], rateb_erp_mw('inventory', 'reports.export', 'inventory-audits'));
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
$router->post($app('supplier-classifications/bulk-delete'), [SupplierClassificationsController::class, 'bulkDestroy'], $supMw);
$router->get($app('supplier-classifications/{id}/documents/panel'), [SupplierClassificationsController::class, 'documentsPanel'], $supMw);
$router->get($app('supplier-classifications/{id}/documents'), [SupplierClassificationsController::class, 'documents'], $supMw);
$router->post($app('supplier-classifications/{id}/documents'), [SupplierClassificationsController::class, 'storeDocument'], $supMw);
$router->post($app('supplier-classifications/{id}/documents/{docId}'), [SupplierClassificationsController::class, 'updateDocument'], $supMw);
$router->post($app('supplier-classifications/{id}/documents/{docId}/delete'), [SupplierClassificationsController::class, 'destroyDocument'], $supMw);

$router->get($app('supplier-kpi'), [SupplierKpiController::class, 'index'], rateb_erp_mw('suppliers', '', 'supplier-kpi'));
$router->get($app('supplier-kpi/export'), [SupplierKpiController::class, 'export'], rateb_erp_mw('suppliers', 'reports.export', 'supplier-kpi'));

$scMw = rateb_erp_mw('suppliers', '', 'supplier-comms');
$router->get($app('supplier-comms'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'index'], $scMw);
$router->get($app('supplier-comms/create'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'create'], $scMw);
$router->post($app('supplier-comms'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'store'], $scMw);
$router->get($app('supplier-comms/{id}/edit'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'edit'], $scMw);
$router->post($app('supplier-comms/{id}'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'update'], $scMw);
$router->post($app('supplier-comms/{id}/delete'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'destroy'], $scMw);
$router->post($app('supplier-comms/bulk-delete'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'bulkDestroy'], $scMw);
$router->get($app('supplier-comms/history'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'supplierHistory'], $scMw);
$router->get($app('supplier-comms/supplier-profile'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'supplierProfile'], $scMw);
$router->get($app('supplier-comms/{id}/print'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'print'], $scMw);
$router->post($app('supplier-comms/{id}/archive'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'archive'], $scMw);

$ctrMw = rateb_erp_mw('contracts', '', 'contract-renewals');
$ctrWriteMw = rateb_erp_mw('contracts', 'contracts.manage', 'contract-renewals');
$router->get($app('contract-renewals'), [ContractRenewalsController::class, 'index'], $ctrMw);
$router->get($app('contract-renewals/export'), [ContractRenewalsController::class, 'export'], rateb_erp_mw('contracts', 'reports.export', 'contract-renewals'));
$router->get($app('contract-renewals/{id}/print'), [ContractRenewalsController::class, 'print'], $ctrMw);
$router->get($app('contract-renewals/{id}/download'), [ContractRenewalsController::class, 'download'], rateb_erp_mw('contracts', 'reports.export', 'contract-renewals'));
$router->get($app('contract-renewals/{id}/edit'), [ContractRenewalsController::class, 'edit'], $ctrWriteMw);
$router->get($app('contract-renewals/{id}'), [ContractRenewalsController::class, 'show'], $ctrMw);
$router->post($app('contract-renewals'), [ContractRenewalsController::class, 'store'], $ctrWriteMw);
$router->post($app('contract-renewals/{id}'), [ContractRenewalsController::class, 'update'], $ctrWriteMw);
$router->post($app('contract-renewals/{id}/approve'), [ContractRenewalsController::class, 'approve'], $ctrWriteMw);
$router->post($app('contract-renewals/{id}/reject'), [ContractRenewalsController::class, 'reject'], $ctrWriteMw);
$router->post($app('contract-renewals/{id}/delete'), [ContractRenewalsController::class, 'destroy'], $ctrWriteMw);
$router->post($app('contract-renewals/bulk-delete'), [ContractRenewalsController::class, 'bulkDestroy'], $ctrWriteMw);

$astMw = rateb_erp_mw('assets', '', 'asset-maintenance');
$astWriteMw = rateb_erp_mw('assets', 'asset_maintenance.manage', 'asset-maintenance');
$astExpMw = rateb_erp_mw('assets', 'reports.export', 'asset-maintenance');
$router->get($app('asset-maintenance'), [AssetMaintenanceController::class, 'index'], $astMw);
$router->get($app('asset-maintenance/export'), [AssetMaintenanceController::class, 'export'], $astExpMw);
$router->get($app('asset-maintenance/{id}/print'), [AssetMaintenanceController::class, 'print'], $astMw);
$router->get($app('asset-maintenance/{id}/download'), [AssetMaintenanceController::class, 'download'], $astExpMw);
$router->get($app('asset-maintenance/{id}'), [AssetMaintenanceController::class, 'show'], $astMw);
$router->post($app('asset-maintenance'), [AssetMaintenanceController::class, 'store'], $astWriteMw);
$router->post($app('asset-maintenance/{id}/approve'), [AssetMaintenanceController::class, 'approve'], $astWriteMw);
$router->post($app('asset-maintenance/{id}/reject'), [AssetMaintenanceController::class, 'reject'], $astWriteMw);
$router->post($app('asset-maintenance/{id}/delete'), [AssetMaintenanceController::class, 'destroy'], $astWriteMw);
$router->post($app('asset-maintenance/bulk-delete'), [AssetMaintenanceController::class, 'bulkDestroy'], $astWriteMw);

$aaMw = rateb_erp_mw('assets', '', 'asset-assignments');
$aaWriteMw = rateb_erp_mw('assets', 'asset_assignments.manage', 'asset-assignments');
$aaExpMw = rateb_erp_mw('assets', 'reports.export', 'asset-assignments');
$router->get($app('asset-assignments'), [AssetAssignmentsController::class, 'index'], $aaMw);
$router->get($app('asset-assignments/export'), [AssetAssignmentsController::class, 'export'], $aaExpMw);
$router->get($app('asset-assignments/{id}/print'), [AssetAssignmentsController::class, 'print'], $aaMw);
$router->get($app('asset-assignments/{id}/download'), [AssetAssignmentsController::class, 'download'], $aaExpMw);
$router->get($app('asset-assignments/{id}'), [AssetAssignmentsController::class, 'show'], $aaMw);
$router->post($app('asset-assignments'), [AssetAssignmentsController::class, 'store'], $aaWriteMw);
$router->post($app('asset-assignments/{id}/approve'), [AssetAssignmentsController::class, 'approve'], $aaWriteMw);
$router->post($app('asset-assignments/{id}/reject'), [AssetAssignmentsController::class, 'reject'], $aaWriteMw);
$router->post($app('asset-assignments/{id}/delete'), [AssetAssignmentsController::class, 'destroy'], $aaWriteMw);
$router->post($app('asset-assignments/bulk-delete'), [AssetAssignmentsController::class, 'bulkDestroy'], $aaWriteMw);

$adMw = rateb_erp_mw('assets', '', 'asset-depreciation');
$adWriteMw = rateb_erp_mw('assets', 'asset_depreciation.manage', 'asset-depreciation');
$router->get($app('asset-depreciation'), [AssetDepreciationController::class, 'index'], $adMw);
$router->get($app('asset-depreciation/export'), [AssetDepreciationController::class, 'export'], rateb_erp_mw('assets', 'reports.export', 'asset-depreciation'));
$router->get($app('asset-depreciation/{id}'), [AssetDepreciationController::class, 'show'], $adMw);
$router->get($app('asset-depreciation/{id}/edit'), [AssetDepreciationController::class, 'edit'], $adWriteMw);
$router->post($app('asset-depreciation'), [AssetDepreciationController::class, 'store'], $adWriteMw);
$router->post($app('asset-depreciation/{id}'), [AssetDepreciationController::class, 'update'], $adWriteMw);
$router->post($app('asset-depreciation/{id}/approve'), [AssetDepreciationController::class, 'approve'], $adWriteMw);
$router->post($app('asset-depreciation/{id}/delete'), [AssetDepreciationController::class, 'destroy'], $adWriteMw);
$router->post($app('asset-depreciation/bulk-delete'), [AssetDepreciationController::class, 'bulkDestroy'], $adWriteMw);

$devMw = rateb_erp_mw('medical_devices', '', 'device-maintenance');
$devWriteMw = rateb_erp_mw('medical_devices', 'device_service.manage', 'device-maintenance');
$devExpMw = rateb_erp_mw('medical_devices', 'reports.export', 'device-maintenance');
$router->get($app('device-maintenance'), [DeviceMaintenanceController::class, 'index'], $devMw);
$router->get($app('device-maintenance/export'), [DeviceMaintenanceController::class, 'export'], $devExpMw);
$router->get($app('device-maintenance/{id}/print'), [DeviceMaintenanceController::class, 'print'], $devMw);
$router->get($app('device-maintenance/{id}/download'), [DeviceMaintenanceController::class, 'download'], $devExpMw);
$router->get($app('device-maintenance/{id}'), [DeviceMaintenanceController::class, 'show'], $devMw);
$router->post($app('device-maintenance'), [DeviceMaintenanceController::class, 'store'], $devWriteMw);
$router->post($app('device-maintenance/{id}/approve'), [DeviceMaintenanceController::class, 'approve'], $devWriteMw);
$router->post($app('device-maintenance/{id}/reject'), [DeviceMaintenanceController::class, 'reject'], $devWriteMw);
$router->post($app('device-maintenance/{id}/delete'), [DeviceMaintenanceController::class, 'destroy'], $devWriteMw);
$router->post($app('device-maintenance/bulk-delete'), [DeviceMaintenanceController::class, 'bulkDestroy'], $devWriteMw);

$dspMw = rateb_erp_mw('medical_devices', '', 'device-spare-parts');
$dspWriteMw = rateb_erp_mw('medical_devices', 'device_spare_parts.manage', 'device-spare-parts');
$dspExpMw = rateb_erp_mw('medical_devices', 'reports.export', 'device-spare-parts');
$router->get($app('device-spare-parts'), [DeviceSparePartsController::class, 'index'], $dspMw);
$router->get($app('device-spare-parts/export'), [DeviceSparePartsController::class, 'export'], $dspExpMw);
$router->get($app('device-spare-parts/{id}/print'), [DeviceSparePartsController::class, 'print'], $dspMw);
$router->get($app('device-spare-parts/{id}/download'), [DeviceSparePartsController::class, 'download'], $dspExpMw);
$router->get($app('device-spare-parts/{id}'), [DeviceSparePartsController::class, 'show'], $dspMw);
$router->post($app('device-spare-parts'), [DeviceSparePartsController::class, 'store'], $dspWriteMw);
$router->post($app('device-spare-parts/{id}/approve'), [DeviceSparePartsController::class, 'approve'], $dspWriteMw);
$router->post($app('device-spare-parts/{id}/reject'), [DeviceSparePartsController::class, 'reject'], $dspWriteMw);
$router->post($app('device-spare-parts/{id}/delete'), [DeviceSparePartsController::class, 'destroy'], $dspWriteMw);
$router->post($app('device-spare-parts/bulk-delete'), [DeviceSparePartsController::class, 'bulkDestroy'], $dspWriteMw);

$dwMw = rateb_erp_mw('medical_devices', '', 'device-warranty');
$dwWriteMw = rateb_erp_mw('medical_devices', 'device_service.manage', 'device-warranty');
$dwExpMw = rateb_erp_mw('medical_devices', 'reports.export', 'device-warranty');
$router->get($app('device-warranty'), [DeviceWarrantyController::class, 'index'], $dwMw);
$router->get($app('device-warranty/export'), [DeviceWarrantyController::class, 'export'], $dwExpMw);
$router->get($app('device-warranty/{id}/print'), [DeviceWarrantyController::class, 'print'], $dwMw);
$router->get($app('device-warranty/{id}/download'), [DeviceWarrantyController::class, 'download'], $dwExpMw);
$router->get($app('device-warranty/{id}'), [DeviceWarrantyController::class, 'show'], $dwMw);
$router->post($app('device-warranty/{id}'), [DeviceWarrantyController::class, 'update'], $dwWriteMw);

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
