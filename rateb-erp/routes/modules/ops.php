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
use Rateb\App\Controllers\Company\OfflineDevicesController;
use Rateb\App\Controllers\Company\BranchDashboardController;
use Rateb\App\Controllers\Company\BranchFinancialReportsController;
use Rateb\App\Controllers\Admin\AccountingControlController;
use Rateb\App\Controllers\Company\InterBranchTransfersController;
use Rateb\App\Controllers\Company\AccountingDashboardController as CompanyAccountingDashboardController;
use Rateb\App\Controllers\Company\HrDashboardController;
use Rateb\App\Controllers\Company\HrApprovalInboxController;
use Rateb\App\Controllers\Company\HrEmployeesController;
use Rateb\App\Controllers\Company\HrEmploymentContractsController;
use Rateb\App\Controllers\Company\HrDepartmentsController;
use Rateb\App\Controllers\Company\HrJobTitlesController;
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
use Rateb\App\Controllers\Company\HrLettersController;
use Rateb\App\Controllers\Company\HrDecisionsController;
use Rateb\App\Controllers\Company\HrDisciplinaryController;
use Rateb\App\Controllers\Company\HrAttendanceBulkController;
use Rateb\App\Controllers\Company\RecruitmentDashboardController;
use Rateb\App\Controllers\Company\RecruitmentCandidatesController;
use Rateb\App\Controllers\Company\RecruitmentAgenciesController;
use Rateb\App\Controllers\Company\RecruitmentInterviewsController;
use Rateb\App\Controllers\Company\RecruitmentChildRecordsController;
use Rateb\App\Controllers\Company\CrmDashboardController;
use Rateb\App\Controllers\Company\CrmLeadsController;
use Rateb\App\Controllers\Company\CrmPipelineController;
use Rateb\App\Controllers\Company\CrmOpportunitiesController;
use Rateb\App\Controllers\Company\CrmQuotationsController;
use Rateb\App\Controllers\Company\CrmMeetingsController;
use Rateb\App\Controllers\Company\CrmTasksController;
use Rateb\App\Controllers\Company\CrmCallsController;
use Rateb\App\Controllers\Company\CrmActivitiesController;
use Rateb\App\Controllers\Company\CrmAutomationController;
use Rateb\App\Controllers\Company\CrmCampaignsController;
use Rateb\App\Controllers\Company\CrmContactsController;
use Rateb\App\Controllers\Company\CrmCompaniesController;
use Rateb\App\Controllers\Company\CrmCustomerProfileController;
use Rateb\App\Controllers\Company\CrmReportsController;
use Rateb\App\Controllers\Company\CrmAdminController;
use Rateb\App\Controllers\Company\CrmTeamsController;
use Rateb\App\Controllers\Company\CrmWorkspaceController;
use Rateb\App\Controllers\Company\CrmDashboardsController;
use Rateb\App\Controllers\Company\CrmIntelligenceController;
use Rateb\App\Controllers\Company\CrmRevenueController;
use Rateb\App\Controllers\Company\CrmForecastController;
use Rateb\App\Controllers\Company\CrmGovernanceController;
use Rateb\App\Controllers\Company\CrmPerformanceController;
use Rateb\App\Controllers\Company\CrmRevOpsController;
use Rateb\App\Controllers\Company\CrmCockpitController;
use Rateb\App\Controllers\Company\CrmWorkflowGovernanceController;
use Rateb\App\Controllers\Company\CrmDataQualityController;
use Rateb\App\Controllers\Company\CrmIntegrityController;
use Rateb\App\Controllers\Company\CrmSearchController;
use Rateb\App\Controllers\Company\CrmReportingCenterController;
use Rateb\App\Controllers\Company\CrmIntelligenceLayerController;
use Rateb\App\Controllers\Company\CrmPredictiveRulesController;
use Rateb\App\Controllers\Company\CrmInsightsController;
use Rateb\App\Controllers\Company\CrmMergeController;
use Rateb\App\Controllers\Company\WebsiteDashboardController;
use Rateb\App\Controllers\Company\WebsitePagesController;
use Rateb\App\Controllers\Company\WebsiteBuilderController;
use Rateb\App\Controllers\Company\WebsiteThemeController;
use Rateb\App\Controllers\Company\WebsiteMediaController;
use Rateb\App\Controllers\Company\WebsiteMenusController;
use Rateb\App\Controllers\Company\WebsiteFormsController;
use Rateb\App\Controllers\Company\ProjectsDashboardController;
use Rateb\App\Controllers\Company\ProjectsController;
use Rateb\App\Controllers\Company\ProjectTasksController;
use Rateb\App\Controllers\Company\ProjectMilestonesController;
use Rateb\App\Controllers\Company\ProjectIssuesController;
use Rateb\App\Controllers\Company\ProjectRisksController;
use Rateb\App\Controllers\Company\ProjectTimesheetsController;
use Rateb\App\Controllers\Company\ProjectResourcesController;
use Rateb\App\Controllers\Company\ProjectBudgetController;
use Rateb\App\Controllers\Company\ProjectTimelineController;
use Rateb\App\Controllers\Company\ProjectReportsController;
use Rateb\App\Controllers\Company\EamDashboardController;
use Rateb\App\Controllers\Company\EamAssetsController;
use Rateb\App\Controllers\Company\EamMaintenanceController;
use Rateb\App\Controllers\Company\EamRequestsController;
use Rateb\App\Controllers\Company\EamWorkOrdersController;
use Rateb\App\Controllers\Company\EamCalendarController;
use Rateb\App\Controllers\Company\EamAssignmentsController;
use Rateb\App\Controllers\Company\EamTimelineController;
use Rateb\App\Controllers\Company\EamInspectionsController;
use Rateb\App\Controllers\Company\EamReportsController;
use Rateb\App\Controllers\Company\ApprovalDashboardController;
use Rateb\App\Controllers\Company\ApprovalRequestsController;
use Rateb\App\Controllers\Company\ApprovalPendingController;
use Rateb\App\Controllers\Company\ApprovalTemplatesController;
use Rateb\App\Controllers\Company\ApprovalChainsController;
use Rateb\App\Controllers\Company\ApprovalRulesController;
use Rateb\App\Controllers\Company\ApprovalHistoryController;
use Rateb\App\Controllers\Company\ApprovalReportsController;
use Rateb\App\Controllers\Company\EprocDashboardController;
use Rateb\App\Controllers\Company\EprocSuppliersController;
use Rateb\App\Controllers\Company\EprocCategoriesController;
use Rateb\App\Controllers\Company\EprocScorecardsController;
use Rateb\App\Controllers\Company\EprocTendersController;
use Rateb\App\Controllers\Company\EprocContractsController;
use Rateb\App\Controllers\Company\EprocCalendarController;
use Rateb\App\Controllers\Company\EprocSpendController;
use Rateb\App\Controllers\Company\EprocPortalController;
use Rateb\App\Controllers\Company\EprocCollaborationController;
use Rateb\App\Controllers\Company\EprocRfqTemplatesController;
use Rateb\App\Controllers\Company\EprocReportsController;
use Rateb\App\Controllers\Company\EprocQualificationController;
use Rateb\App\Controllers\Company\MfgDashboardController;
use Rateb\App\Controllers\Company\MfgProductsController;
use Rateb\App\Controllers\Company\MfgBomsController;
use Rateb\App\Controllers\Company\MfgProductionOrdersController;
use Rateb\App\Controllers\Company\MfgWorkOrdersController;
use Rateb\App\Controllers\Company\MfgWorkCentersController;
use Rateb\App\Controllers\Company\MfgRoutingsController;
use Rateb\App\Controllers\Company\MfgCapacityController;
use Rateb\App\Controllers\Company\MfgCalendarController;
use Rateb\App\Controllers\Company\MfgSchedulesController;
use Rateb\App\Controllers\Company\MfgQualityController;
use Rateb\App\Controllers\Company\MfgReportsController;
use Rateb\App\Controllers\Company\HrmDashboardController;
use Rateb\App\Controllers\Company\HrmEmployeesController;
use Rateb\App\Controllers\Company\HrmDepartmentsController;
use Rateb\App\Controllers\Company\HrmPositionsController;
use Rateb\App\Controllers\Company\HrmOrganizationController;
use Rateb\App\Controllers\Company\HrmTrainingController;
use Rateb\App\Controllers\Company\HrmPerformanceController;
use Rateb\App\Controllers\Company\HrmPromotionsController;
use Rateb\App\Controllers\Company\HrmTransfersController;
use Rateb\App\Controllers\Company\HrmGoalsController;
use Rateb\App\Controllers\Company\HrmCompetenciesController;
use Rateb\App\Controllers\Company\HrmReportsController;
use Rateb\App\Controllers\Company\HrmTimelineController;
use Rateb\App\Controllers\Company\PayrollPlatformController;
use Rateb\App\Controllers\Company\PayrollDashboardController;
use Rateb\App\Controllers\Company\PayrollCyclesController;
use Rateb\App\Controllers\Company\PayrollBatchesController;
use Rateb\App\Controllers\Company\PayrollPayslipsController;
use Rateb\App\Controllers\Company\PayrollLoansController;
use Rateb\App\Controllers\Company\PayrollAdvancesController;
use Rateb\App\Controllers\Company\PayrollOvertimeController;
use Rateb\App\Controllers\Company\PayrollSalaryStructuresController;
use Rateb\App\Controllers\Company\PayrollReportsController;
use Rateb\App\Controllers\Company\PayrollTimelinePageController;
use Rateb\App\Controllers\Company\QualityPlatformController;
use Rateb\App\Controllers\Company\QualityDashboardController;
use Rateb\App\Controllers\Company\QualityPlansController;
use Rateb\App\Controllers\Company\QualityStandardsController;
use Rateb\App\Controllers\Company\QualityChecklistsController;
use Rateb\App\Controllers\Company\QualityInspectionsController;
use Rateb\App\Controllers\Company\QualityDefectsController;
use Rateb\App\Controllers\Company\QualityNonconformitiesController;
use Rateb\App\Controllers\Company\QualityCorrectiveActionsController;
use Rateb\App\Controllers\Company\QualityPreventiveActionsController;
use Rateb\App\Controllers\Company\QualityAuditsController;
use Rateb\App\Controllers\Company\QualityComplaintsController;
use Rateb\App\Controllers\Company\QualitySupplierQualityController;
use Rateb\App\Controllers\Company\QualityReportsController;
use Rateb\App\Controllers\Company\QualityTimelinePageController;
use Rateb\App\Controllers\Company\DocumentManagementPlatformController;
use Rateb\App\Controllers\Company\DmsDashboardController;
use Rateb\App\Controllers\Company\DmsRepositoriesController;
use Rateb\App\Controllers\Company\DmsFoldersController;
use Rateb\App\Controllers\Company\DmsDocumentsController;
use Rateb\App\Controllers\Company\DmsVersionsController;
use Rateb\App\Controllers\Company\DmsSearchController;
use Rateb\App\Controllers\Company\DmsFavoritesController;
use Rateb\App\Controllers\Company\DmsSharesController;
use Rateb\App\Controllers\Company\DmsRetentionController;
use Rateb\App\Controllers\Company\DmsLegalHoldsController;
use Rateb\App\Controllers\Company\DmsPermissionsController;
use Rateb\App\Controllers\Company\DmsTimelinePageController;
use Rateb\App\Controllers\Company\DmsReportsController;
use Rateb\App\Controllers\Company\BiPlatformController;
use Rateb\App\Controllers\Company\BiDashboardController;
use Rateb\App\Controllers\Company\BiDashboardsController;
use Rateb\App\Controllers\Company\BiKpisController;
use Rateb\App\Controllers\Company\BiReportsController;
use Rateb\App\Controllers\Company\BiWidgetsController;
use Rateb\App\Controllers\Company\BiDatasetsController;
use Rateb\App\Controllers\Company\BiAlertsController;
use Rateb\App\Controllers\Company\BiSchedulesController;
use Rateb\App\Controllers\Company\BiExportsController;
use Rateb\App\Controllers\Company\BiTrendsController;
use Rateb\App\Controllers\Company\BiForecastsController;
use Rateb\App\Controllers\Company\BiScopesController;
use Rateb\App\Controllers\Company\BiAnalyticsController;
use Rateb\App\Controllers\Company\BiTimelinePageController;
use Rateb\App\Controllers\Company\ChartOfAccountsController as CompanyChartOfAccountsController;
use Rateb\App\Controllers\Company\ProductCategoriesController;
use Rateb\App\Controllers\Company\StockMovementsController;
use Rateb\App\Controllers\Company\DocumentsController;
use Rateb\App\Controllers\Company\JournalEntriesController as CompanyJournalEntriesController;
use Rateb\App\Controllers\Company\CashVouchersController as CompanyCashVouchersController;
use Rateb\App\Controllers\Company\FiscalPeriodsController as CompanyFiscalPeriodsController;
use Rateb\App\Controllers\Company\CostCentersController as CompanyCostCentersController;
use Rateb\App\Controllers\Company\CustomersController as CompanyCustomersController;
use Rateb\App\Controllers\Company\BankAccountsController as CompanyBankAccountsController;
use Rateb\App\Controllers\Company\AccountingPlatformHubController;
use Rateb\App\Controllers\Company\AccountingCurrenciesController;
use Rateb\App\Controllers\Company\AccountingTaxCodesController;
use Rateb\App\Controllers\Company\AccountingProfitCentersController;
use Rateb\App\Controllers\Company\AccountingExchangeRatesController;
use Rateb\App\Controllers\Company\AccountingRecurringController;
use Rateb\App\Controllers\Company\AccountingOpeningBalancesController;
use Rateb\App\Controllers\Company\AccountingWorkflowController;
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
use Rateb\App\Core\Middleware\CompanySaaSMiddleware;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;
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

foreach ($moduleRoutes as $path => [$class, $module]) {
    $mw = rateb_erp_mw($module, '', $path);
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
    $router->get($app($path . '/export'), [$class, 'export'], rateb_erp_mw($module, 'reports.export', $path));
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
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

$invImgMw = [ErpAuthMiddleware::class, CompanySaaSMiddleware::class];
$router->get($app('inventory/warehouse-items'), [InventoryController::class, 'warehouseItemsJson'], rateb_erp_mw('inventory', '', 'inventory'));
$router->get($app('inventory/{id}/image'), [InventoryController::class, 'image'], $invImgMw);
$router->post($app('inventory/{id}/transfer-to-pos-warehouse'), [InventoryController::class, 'transferToPosWarehouse'], rateb_erp_mw('inventory', '', 'inventory'));

$router->get($app('purchase-requests/line-attachment/{itemId}'), [PurchaseRequestsController::class, 'downloadLineAttachment'], rateb_erp_mw('procurement', '', 'purchase-requests'));
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
$router->get($app('hr/approvals-inbox'), [HrApprovalInboxController::class, 'index'], rateb_erp_mw('hr', '', 'hr'));
// Decide auth is server-side (matrix user/role/oversight) — do not require hr.manage at the route.
$router->post($app('hr/approvals-inbox/decide'), [HrApprovalInboxController::class, 'decide'], rateb_erp_mw('hr', '', 'hr'));

$hrEmpMw = rateb_erp_mw('hr', '', 'hr-employees');
$router->get($app('hr/employment-contracts'), [HrEmploymentContractsController::class, 'index'], $hrEmpMw);
$router->post($app('hr/employment-contracts'), [HrEmploymentContractsController::class, 'store'], $hrEmpMw);
$router->get($app('hr/employment-contracts/{id}'), [HrEmploymentContractsController::class, 'show'], $hrEmpMw);
$router->post($app('hr/employment-contracts/{id}/update'), [HrEmploymentContractsController::class, 'update'], $hrEmpMw);
$router->post($app('hr/employment-contracts/{id}/activate'), [HrEmploymentContractsController::class, 'activate'], $hrEmpMw);
$router->post($app('hr/employment-contracts/{id}/terminate'), [HrEmploymentContractsController::class, 'terminate'], $hrEmpMw);

/** Phase 15A — Recruitment ONLINE (no offline hooks). */
$recMw = rateb_erp_mw('recruitment', '', 'recruitment-candidates');
$router->get($app('recruitment'), [RecruitmentDashboardController::class, 'index'], $recMw);
$router->get($app('recruitment/candidates'), [RecruitmentCandidatesController::class, 'index'], $recMw);
$router->get($app('recruitment/candidates/create'), [RecruitmentCandidatesController::class, 'create'], $recMw);
$router->post($app('recruitment/candidates'), [RecruitmentCandidatesController::class, 'store'], $recMw);
$router->get($app('recruitment/candidates/{id}'), [RecruitmentCandidatesController::class, 'show'], $recMw);
$router->get($app('recruitment/candidates/{id}/edit'), [RecruitmentCandidatesController::class, 'edit'], $recMw);
$router->post($app('recruitment/candidates/{id}'), [RecruitmentCandidatesController::class, 'update'], $recMw);
$router->post($app('recruitment/candidates/{id}/delete'), [RecruitmentCandidatesController::class, 'destroy'], $recMw);
$router->post($app('recruitment/candidates/{id}/transition'), [RecruitmentCandidatesController::class, 'transition'], $recMw);
$router->post($app('recruitment/candidates/{id}/documents'), [RecruitmentCandidatesController::class, 'storeDocument'], $recMw);
$router->post($app('recruitment/candidates/{id}/interview'), [RecruitmentInterviewsController::class, 'store'], $recMw);
$router->post($app('recruitment/candidates/{id}/visa'), [RecruitmentChildRecordsController::class, 'storeVisa'], $recMw);
$router->post($app('recruitment/candidates/{id}/medical'), [RecruitmentChildRecordsController::class, 'storeMedical'], $recMw);
$router->post($app('recruitment/candidates/{id}/contract'), [RecruitmentChildRecordsController::class, 'storeContract'], $recMw);
$router->post($app('recruitment/candidates/{id}/passport'), [RecruitmentChildRecordsController::class, 'storePassport'], $recMw);
$router->post($app('recruitment/candidates/{id}/assign'), [RecruitmentChildRecordsController::class, 'storeAssignment'], $recMw);
$router->get($app('recruitment/agencies'), [RecruitmentAgenciesController::class, 'index'], $recMw);
$router->get($app('recruitment/agencies/create'), [RecruitmentAgenciesController::class, 'create'], $recMw);
$router->post($app('recruitment/agencies'), [RecruitmentAgenciesController::class, 'store'], $recMw);

/** Phase 17A — CRM ONLINE (no offline hooks). */
$crmMw = rateb_erp_mw('crm', '', 'crm');
$router->get($app('crm'), [CrmDashboardController::class, 'index'], $crmMw);
$router->get($app('crm/leads'), [CrmLeadsController::class, 'index'], $crmMw);
$router->get($app('crm/leads/board'), [CrmLeadsController::class, 'board'], $crmMw);
$router->get($app('crm/leads/create'), [CrmLeadsController::class, 'create'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->post($app('crm/leads'), [CrmLeadsController::class, 'store'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/leads/{id}'), [CrmLeadsController::class, 'show'], $crmMw);
$router->get($app('crm/leads/{id}/edit'), [CrmLeadsController::class, 'edit'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/leads/{id}'), [CrmLeadsController::class, 'update'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/leads/{id}/delete'), [CrmLeadsController::class, 'destroy'], rateb_erp_mw('crm', 'crm.delete', 'crm'));
$router->post($app('crm/leads/{id}/transition'), [CrmLeadsController::class, 'transition'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/leads/{id}/assign'), [CrmLeadsController::class, 'assign'], rateb_erp_mw('crm', 'crm.assign', 'crm'));
$router->post($app('crm/leads/{id}/notes'), [CrmLeadsController::class, 'storeNote'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/leads/{id}/convert-opportunity'), [CrmLeadsController::class, 'convertToOpportunity'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/pipeline'), [CrmPipelineController::class, 'index'], rateb_erp_mw('crm', 'crm.pipeline', 'crm'));
$router->post($app('crm/pipeline'), [CrmPipelineController::class, 'storePipeline'], rateb_erp_mw('crm', 'crm.pipeline', 'crm'));
$router->post($app('crm/pipeline/stages'), [CrmPipelineController::class, 'storeStage'], rateb_erp_mw('crm', 'crm.pipeline', 'crm'));
$router->post($app('crm/pipeline/loss-reasons'), [CrmPipelineController::class, 'storeLossReason'], rateb_erp_mw('crm', 'crm.pipeline', 'crm'));
$router->post($app('crm/opportunities/{id}/move-stage'), [CrmPipelineController::class, 'moveOpportunity'], rateb_erp_mw('crm', 'crm.pipeline', 'crm'));
$router->get($app('crm/reports'), [CrmReportsController::class, 'index'], rateb_erp_mw('crm', 'crm.reports.view', 'crm'));
$router->post($app('crm/reports/snapshot'), [CrmReportsController::class, 'snapshot'], rateb_erp_mw('crm', 'crm.forecast.manage', 'crm'));
$router->get($app('crm/reports/export'), [CrmReportsController::class, 'export'], rateb_erp_mw('crm', 'crm.export.manage', 'crm'));
$router->post($app('crm/reports/filters'), [CrmReportsController::class, 'saveFilter'], rateb_erp_mw('crm', 'crm.export.manage', 'crm'));
$router->post($app('crm/automation/run'), [CrmAutomationController::class, 'run'], rateb_erp_mw('crm', 'crm.admin', 'crm'));
$router->get($app('crm/admin'), [CrmAdminController::class, 'index'], rateb_erp_mw('crm', 'crm.config.manage', 'crm'));
$router->post($app('crm/admin/activity-types'), [CrmAdminController::class, 'storeActivityType'], rateb_erp_mw('crm', 'crm.config.manage', 'crm'));
$router->post($app('crm/admin/automation-rules/{id}'), [CrmAdminController::class, 'updateAutomationRule'], rateb_erp_mw('crm', 'crm.config.manage', 'crm'));
$router->get($app('crm/workspace'), [CrmWorkspaceController::class, 'index'], rateb_erp_mw('crm', 'crm.workspace.view', 'crm'));
$router->get($app('crm/dashboards'), [CrmDashboardsController::class, 'index'], rateb_erp_mw('crm', 'crm.dashboards.view', 'crm'));
$router->post($app('crm/intelligence/refresh'), [CrmIntelligenceController::class, 'refresh'], rateb_erp_mw('crm', 'crm.intelligence.view', 'crm'));
$router->post($app('crm/opportunities/{id}/score'), [CrmIntelligenceController::class, 'scoreOpportunity'], rateb_erp_mw('crm', 'crm.intelligence.view', 'crm'));
$router->get($app('crm/revenue'), [CrmRevenueController::class, 'index'], rateb_erp_mw('crm', 'crm.revenue.intel', 'crm'));
$router->get($app('crm/forecast'), [CrmForecastController::class, 'index'], rateb_erp_mw('crm', 'crm.forecast.enterprise', 'crm'));
$router->post($app('crm/forecast/snapshot'), [CrmForecastController::class, 'snapshot'], rateb_erp_mw('crm', 'crm.forecast.enterprise', 'crm'));
$router->get($app('crm/governance'), [CrmGovernanceController::class, 'index'], rateb_erp_mw('crm', 'crm.governance.view', 'crm'));
$router->post($app('crm/governance/scan'), [CrmGovernanceController::class, 'scan'], rateb_erp_mw('crm', 'crm.governance.manage', 'crm'));
$router->post($app('crm/governance/issues/{id}/resolve'), [CrmGovernanceController::class, 'resolve'], rateb_erp_mw('crm', 'crm.governance.manage', 'crm'));
$router->post($app('crm/governance/settings'), [CrmGovernanceController::class, 'saveSetting'], rateb_erp_mw('crm', 'crm.governance.manage', 'crm'));
$router->get($app('crm/performance'), [CrmPerformanceController::class, 'index'], rateb_erp_mw('crm', 'crm.performance.view', 'crm'));
$router->get($app('crm/revops'), [CrmRevOpsController::class, 'index'], rateb_erp_mw('crm', 'crm.revops.view', 'crm'));
$router->post($app('crm/revops/automation'), [CrmRevOpsController::class, 'runAutomation'], rateb_erp_mw('crm', 'crm.revops.run', 'crm'));
$router->get($app('crm/cockpit'), [CrmCockpitController::class, 'index'], rateb_erp_mw('crm', 'crm.cockpit.view', 'crm'));
$router->get($app('crm/workflow-governance'), [CrmWorkflowGovernanceController::class, 'index'], rateb_erp_mw('crm', 'crm.workflow.governance', 'crm'));
$router->post($app('crm/workflow-governance/rules'), [CrmWorkflowGovernanceController::class, 'saveRule'], rateb_erp_mw('crm', 'crm.workflow.governance', 'crm'));
$router->get($app('crm/data-quality'), [CrmDataQualityController::class, 'index'], rateb_erp_mw('crm', 'crm.governance.view', 'crm'));
$router->post($app('crm/data-quality/scan'), [CrmDataQualityController::class, 'scan'], rateb_erp_mw('crm', 'crm.governance.manage', 'crm'));
$router->post($app('crm/data-quality/issues/{id}/resolve'), [CrmDataQualityController::class, 'resolve'], rateb_erp_mw('crm', 'crm.governance.manage', 'crm'));
$router->get($app('crm/integrity'), [CrmIntegrityController::class, 'index'], rateb_erp_mw('crm', 'crm.governance.view', 'crm'));
$router->get($app('crm/search'), [CrmSearchController::class, 'index'], rateb_erp_mw('crm', 'crm.search.view', 'crm'));
$router->get($app('crm/reporting-center'), [CrmReportingCenterController::class, 'index'], rateb_erp_mw('crm', 'crm.reporting.center', 'crm'));
$router->post($app('crm/reporting-center/dashboards'), [CrmReportingCenterController::class, 'saveDashboard'], rateb_erp_mw('crm', 'crm.reporting.center', 'crm'));
$router->post($app('crm/reporting-center/schedules'), [CrmReportingCenterController::class, 'saveSchedule'], rateb_erp_mw('crm', 'crm.reporting.center', 'crm'));
$router->post($app('crm/reporting-center/run-due'), [CrmReportingCenterController::class, 'runDue'], rateb_erp_mw('crm', 'crm.reporting.center', 'crm'));
$router->get($app('crm/intelligence-layer'), [CrmIntelligenceLayerController::class, 'index'], rateb_erp_mw('crm', 'crm.intelligence.advanced', 'crm'));
$router->get($app('crm/predictive'), [CrmPredictiveRulesController::class, 'index'], rateb_erp_mw('crm', 'crm.predictive.manage', 'crm'));
$router->post($app('crm/predictive/rules'), [CrmPredictiveRulesController::class, 'save'], rateb_erp_mw('crm', 'crm.predictive.manage', 'crm'));
$router->get($app('crm/insights'), [CrmInsightsController::class, 'index'], rateb_erp_mw('crm', 'crm.insights.view', 'crm'));
$router->post($app('crm/insights/{id}/dismiss'), [CrmInsightsController::class, 'dismiss'], rateb_erp_mw('crm', 'crm.insights.manage', 'crm'));
$router->get($app('crm/merge'), [CrmMergeController::class, 'index'], rateb_erp_mw('crm', 'crm.merge.manage', 'crm'));
$router->post($app('crm/merge/request'), [CrmMergeController::class, 'request'], rateb_erp_mw('crm', 'crm.merge.manage', 'crm'));
$router->post($app('crm/merge/{id}/execute'), [CrmMergeController::class, 'execute'], rateb_erp_mw('crm', 'crm.merge.manage', 'crm'));
$router->post($app('crm/merge/{id}/reject'), [CrmMergeController::class, 'reject'], rateb_erp_mw('crm', 'crm.merge.manage', 'crm'));
$router->post($app('crm/merge/freshness'), [CrmMergeController::class, 'freshnessScan'], rateb_erp_mw('crm', 'crm.merge.manage', 'crm'));
$router->get($app('crm/teams'), [CrmTeamsController::class, 'index'], rateb_erp_mw('crm', 'crm.teams.view', 'crm'));
$router->post($app('crm/teams'), [CrmTeamsController::class, 'storeTeam'], rateb_erp_mw('crm', 'crm.teams.manage', 'crm'));
$router->post($app('crm/teams/{id}/members'), [CrmTeamsController::class, 'storeMember'], rateb_erp_mw('crm', 'crm.teams.manage', 'crm'));
$router->post($app('crm/teams/territories'), [CrmTeamsController::class, 'storeTerritory'], rateb_erp_mw('crm', 'crm.teams.manage', 'crm'));
$router->post($app('crm/teams/ownership-rules'), [CrmTeamsController::class, 'storeOwnershipRule'], rateb_erp_mw('crm', 'crm.teams.manage', 'crm'));
$router->get($app('crm/opportunities'), [CrmOpportunitiesController::class, 'index'], $crmMw);
$router->get($app('crm/opportunities/create'), [CrmOpportunitiesController::class, 'create'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->post($app('crm/opportunities'), [CrmOpportunitiesController::class, 'store'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/opportunities/{id}'), [CrmOpportunitiesController::class, 'show'], $crmMw);
$router->post($app('crm/opportunities/{id}/convert-quotation'), [CrmOpportunitiesController::class, 'convertToQuotation'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/quotations'), [CrmQuotationsController::class, 'index'], $crmMw);
$router->get($app('crm/quotations/create'), [CrmQuotationsController::class, 'create'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->post($app('crm/quotations'), [CrmQuotationsController::class, 'store'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/quotations/{id}'), [CrmQuotationsController::class, 'show'], $crmMw);
$router->post($app('crm/quotations/{id}/transition'), [CrmQuotationsController::class, 'transition'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/quotations/{id}/convert-customer'), [CrmQuotationsController::class, 'convertToCustomer'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/quotations/{id}/duplicate'), [CrmQuotationsController::class, 'duplicate'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->post($app('crm/quotations/{id}/version'), [CrmQuotationsController::class, 'version'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->post($app('crm/quotations/{id}/submit-approval'), [CrmQuotationsController::class, 'submitApproval'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->post($app('crm/quotations/{id}/decide-approval'), [CrmQuotationsController::class, 'decideApproval'], rateb_erp_mw('crm', 'crm.update', 'crm'));
$router->get($app('crm/meetings'), [CrmMeetingsController::class, 'index'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->post($app('crm/meetings'), [CrmMeetingsController::class, 'store'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->get($app('crm/tasks'), [CrmTasksController::class, 'index'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->post($app('crm/tasks'), [CrmTasksController::class, 'store'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->post($app('crm/tasks/{id}/complete'), [CrmTasksController::class, 'complete'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->get($app('crm/calls'), [CrmCallsController::class, 'index'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->post($app('crm/calls'), [CrmCallsController::class, 'store'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->get($app('crm/activities'), [CrmActivitiesController::class, 'index'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->post($app('crm/activities'), [CrmActivitiesController::class, 'store'], rateb_erp_mw('crm', 'crm.activities', 'crm'));
$router->get($app('crm/campaigns'), [CrmCampaignsController::class, 'index'], rateb_erp_mw('crm', 'crm.campaign', 'crm'));
$router->post($app('crm/campaigns'), [CrmCampaignsController::class, 'store'], rateb_erp_mw('crm', 'crm.campaign', 'crm'));
$router->get($app('crm/contacts'), [CrmContactsController::class, 'index'], $crmMw);
$router->post($app('crm/contacts'), [CrmContactsController::class, 'store'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/contacts/{id}'), [CrmContactsController::class, 'show'], $crmMw);
$router->get($app('crm/companies'), [CrmCompaniesController::class, 'index'], $crmMw);
$router->post($app('crm/companies'), [CrmCompaniesController::class, 'store'], rateb_erp_mw('crm', 'crm.create', 'crm'));
$router->get($app('crm/companies/{id}'), [CrmCompaniesController::class, 'show'], $crmMw);
$router->get($app('crm/customers/{id}'), [CrmCustomerProfileController::class, 'show'], $crmMw);
$router->post($app('crm/customers/{id}/lifecycle'), [CrmCustomerProfileController::class, 'transitionLifecycle'], rateb_erp_mw('crm', 'crm.lifecycle.manage', 'crm'));
$router->post($app('crm/customers/{id}/ownership'), [CrmCustomerProfileController::class, 'assignOwnership'], rateb_erp_mw('crm', 'crm.lifecycle.manage', 'crm'));
$router->post($app('crm/customers/{id}/renewal'), [CrmCustomerProfileController::class, 'setRenewal'], rateb_erp_mw('crm', 'crm.lifecycle.manage', 'crm'));

/** Phase 18A — Projects ONLINE (no offline hooks). */
$prjMw = rateb_erp_mw('projects', '', 'projects');
$router->get($app('projects'), [ProjectsDashboardController::class, 'index'], $prjMw);
$router->get($app('projects/list'), [ProjectsController::class, 'index'], $prjMw);
$router->get($app('projects/create'), [ProjectsController::class, 'create'], rateb_erp_mw('projects', 'projects.create', 'projects'));
$router->post($app('projects'), [ProjectsController::class, 'store'], rateb_erp_mw('projects', 'projects.create', 'projects'));
$router->get($app('projects/tasks'), [ProjectTasksController::class, 'index'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->post($app('projects/tasks'), [ProjectTasksController::class, 'store'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->get($app('projects/tasks/kanban'), [ProjectTasksController::class, 'kanban'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->get($app('projects/tasks/gantt'), [ProjectTasksController::class, 'gantt'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->get($app('projects/tasks/calendar'), [ProjectTasksController::class, 'calendar'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->post($app('projects/tasks/{id}/transition'), [ProjectTasksController::class, 'transition'], rateb_erp_mw('projects', 'projects.tasks', 'projects'));
$router->get($app('projects/milestones'), [ProjectMilestonesController::class, 'index'], $prjMw);
$router->post($app('projects/milestones'), [ProjectMilestonesController::class, 'store'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->get($app('projects/issues'), [ProjectIssuesController::class, 'index'], $prjMw);
$router->post($app('projects/issues'), [ProjectIssuesController::class, 'store'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->get($app('projects/risks'), [ProjectRisksController::class, 'index'], $prjMw);
$router->post($app('projects/risks'), [ProjectRisksController::class, 'store'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->get($app('projects/timesheets'), [ProjectTimesheetsController::class, 'index'], rateb_erp_mw('projects', 'projects.timesheets', 'projects'));
$router->post($app('projects/timesheets'), [ProjectTimesheetsController::class, 'store'], rateb_erp_mw('projects', 'projects.timesheets', 'projects'));
$router->get($app('projects/resources'), [ProjectResourcesController::class, 'index'], $prjMw);
$router->post($app('projects/resources'), [ProjectResourcesController::class, 'store'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->get($app('projects/budget'), [ProjectBudgetController::class, 'index'], rateb_erp_mw('projects', 'projects.budget', 'projects'));
$router->post($app('projects/budget'), [ProjectBudgetController::class, 'storeBudget'], rateb_erp_mw('projects', 'projects.budget', 'projects'));
$router->post($app('projects/budget/costs'), [ProjectBudgetController::class, 'storeCost'], rateb_erp_mw('projects', 'projects.budget', 'projects'));
$router->get($app('projects/timeline'), [ProjectTimelineController::class, 'index'], $prjMw);
$router->post($app('projects/timeline/activities'), [ProjectTimelineController::class, 'storeActivity'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->get($app('projects/reports'), [ProjectReportsController::class, 'index'], rateb_erp_mw('projects', 'projects.reports', 'projects'));
$router->get($app('projects/{id}'), [ProjectsController::class, 'show'], $prjMw);
$router->get($app('projects/{id}/edit'), [ProjectsController::class, 'edit'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->post($app('projects/{id}'), [ProjectsController::class, 'update'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->post($app('projects/{id}/delete'), [ProjectsController::class, 'destroy'], rateb_erp_mw('projects', 'projects.delete', 'projects'));
$router->post($app('projects/{id}/transition'), [ProjectsController::class, 'transition'], rateb_erp_mw('projects', 'projects.update', 'projects'));
$router->post($app('projects/{id}/assign'), [ProjectsController::class, 'assign'], rateb_erp_mw('projects', 'projects.assign', 'projects'));
$router->post($app('projects/{id}/comments'), [ProjectsController::class, 'storeComment'], rateb_erp_mw('projects', 'projects.update', 'projects'));

/** Phase 19A — Enterprise Assets & Maintenance ONLINE (eam/*; legacy /assets untouched). */
$eamMw = rateb_erp_mw('assets', 'assets.view', 'assets');
$router->get($app('eam'), [EamDashboardController::class, 'index'], $eamMw);
$router->get($app('eam/assets'), [EamAssetsController::class, 'index'], $eamMw);
$router->get($app('eam/assets/create'), [EamAssetsController::class, 'create'], rateb_erp_mw('assets', 'assets.create', 'assets'));
$router->post($app('eam/assets'), [EamAssetsController::class, 'store'], rateb_erp_mw('assets', 'assets.create', 'assets'));
$router->get($app('eam/maintenance'), [EamMaintenanceController::class, 'index'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->post($app('eam/maintenance/plans'), [EamMaintenanceController::class, 'storePlan'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/requests'), [EamRequestsController::class, 'index'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->post($app('eam/requests'), [EamRequestsController::class, 'store'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/requests/{id}'), [EamRequestsController::class, 'show'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->post($app('eam/requests/{id}/transition'), [EamRequestsController::class, 'transition'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/work-orders'), [EamWorkOrdersController::class, 'index'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->post($app('eam/work-orders'), [EamWorkOrdersController::class, 'store'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/work-orders/{id}'), [EamWorkOrdersController::class, 'show'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->post($app('eam/work-orders/{id}/transition'), [EamWorkOrdersController::class, 'transition'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/calendar'), [EamCalendarController::class, 'index'], rateb_erp_mw('assets', 'assets.maintenance', 'assets'));
$router->get($app('eam/assignments'), [EamAssignmentsController::class, 'index'], rateb_erp_mw('assets', 'assets.assign', 'assets'));
$router->get($app('eam/timeline'), [EamTimelineController::class, 'index'], $eamMw);
$router->post($app('eam/timeline/activities'), [EamTimelineController::class, 'storeActivity'], rateb_erp_mw('assets', 'assets.update', 'assets'));
$router->get($app('eam/inspections'), [EamInspectionsController::class, 'index'], rateb_erp_mw('assets', 'assets.inspection', 'assets'));
$router->post($app('eam/inspections'), [EamInspectionsController::class, 'store'], rateb_erp_mw('assets', 'assets.inspection', 'assets'));
$router->get($app('eam/reports'), [EamReportsController::class, 'index'], $eamMw);
$router->get($app('eam/assets/{id}'), [EamAssetsController::class, 'show'], $eamMw);
$router->get($app('eam/assets/{id}/edit'), [EamAssetsController::class, 'edit'], rateb_erp_mw('assets', 'assets.update', 'assets'));
$router->post($app('eam/assets/{id}'), [EamAssetsController::class, 'update'], rateb_erp_mw('assets', 'assets.update', 'assets'));
$router->post($app('eam/assets/{id}/delete'), [EamAssetsController::class, 'destroy'], rateb_erp_mw('assets', 'assets.delete', 'assets'));
$router->post($app('eam/assets/{id}/transition'), [EamAssetsController::class, 'transition'], rateb_erp_mw('assets', 'assets.update', 'assets'));
$router->post($app('eam/assets/{id}/assign'), [EamAssetsController::class, 'assign'], rateb_erp_mw('assets', 'assets.assign', 'assets'));
$router->post($app('eam/assets/{id}/transfer'), [EamAssetsController::class, 'transfer'], rateb_erp_mw('assets', 'assets.transfer', 'assets'));
$router->post($app('eam/assets/{id}/comments'), [EamAssetsController::class, 'storeComment'], rateb_erp_mw('assets', 'assets.update', 'assets'));

/** Phase 20A — Enterprise Approval Platform ONLINE (approvals/*; legacy WorkflowService / rateb_approval_* untouched). */
$aprMw = rateb_erp_mw('approval', 'approval.view', 'approval');
$router->get($app('approvals'), [ApprovalDashboardController::class, 'index'], $aprMw);
$router->get($app('approvals/requests'), [ApprovalRequestsController::class, 'index'], $aprMw);
$router->get($app('approvals/requests/create'), [ApprovalRequestsController::class, 'create'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->post($app('approvals/requests'), [ApprovalRequestsController::class, 'store'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->get($app('approvals/pending'), [ApprovalPendingController::class, 'index'], rateb_erp_mw('approval', 'approval.approve', 'approval'));
$router->get($app('approvals/templates'), [ApprovalTemplatesController::class, 'index'], $aprMw);
$router->post($app('approvals/templates'), [ApprovalTemplatesController::class, 'store'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->post($app('approvals/templates/stages'), [ApprovalTemplatesController::class, 'storeStage'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->get($app('approvals/chains'), [ApprovalChainsController::class, 'index'], $aprMw);
$router->post($app('approvals/chains'), [ApprovalChainsController::class, 'store'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->get($app('approvals/rules'), [ApprovalRulesController::class, 'index'], $aprMw);
$router->post($app('approvals/rules'), [ApprovalRulesController::class, 'store'], rateb_erp_mw('approval', 'approval.create', 'approval'));
$router->get($app('approvals/history'), [ApprovalHistoryController::class, 'index'], $aprMw);
$router->get($app('approvals/reports'), [ApprovalReportsController::class, 'index'], $aprMw);
$router->get($app('approvals/requests/{id}'), [ApprovalRequestsController::class, 'show'], $aprMw);
$router->post($app('approvals/requests/{id}/transition'), [ApprovalRequestsController::class, 'transition'], rateb_erp_mw('approval', 'approval.submit', 'approval'));
$router->post($app('approvals/requests/{id}/comments'), [ApprovalRequestsController::class, 'storeComment'], rateb_erp_mw('approval', 'approval.view', 'approval'));
$router->post($app('approvals/requests/{id}/delegate'), [ApprovalRequestsController::class, 'storeDelegation'], rateb_erp_mw('approval', 'approval.delegate', 'approval'));

/** Phase 21A eproc — retired. Lean procurement only (PR/PO/RFQ/quotations). Set RATEB_EPROC_ENABLED=1 to re-enable. */
if (filter_var(getenv('RATEB_EPROC_ENABLED') ?: ($_ENV['RATEB_EPROC_ENABLED'] ?? ''), FILTER_VALIDATE_BOOLEAN)) {
$eprocMw = rateb_erp_mw('procurement', 'procurement.view', 'procurement');
$router->get($app('eproc'), [EprocDashboardController::class, 'index'], $eprocMw);
$router->get($app('eproc/suppliers'), [EprocSuppliersController::class, 'index'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->get($app('eproc/suppliers/create'), [EprocSuppliersController::class, 'create'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->post($app('eproc/suppliers'), [EprocSuppliersController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/categories'), [EprocCategoriesController::class, 'index'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/categories'), [EprocCategoriesController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/scorecards'), [EprocScorecardsController::class, 'index'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/scorecards'), [EprocScorecardsController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/tenders'), [EprocTendersController::class, 'index'], rateb_erp_mw('procurement', 'procurement.tender', 'procurement'));
$router->get($app('eproc/tenders/create'), [EprocTendersController::class, 'create'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->post($app('eproc/tenders'), [EprocTendersController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/contracts'), [EprocContractsController::class, 'index'], rateb_erp_mw('procurement', 'procurement.contract', 'procurement'));
$router->get($app('eproc/contracts/create'), [EprocContractsController::class, 'create'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->post($app('eproc/contracts'), [EprocContractsController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/calendar'), [EprocCalendarController::class, 'index'], $eprocMw);
$router->post($app('eproc/calendar'), [EprocCalendarController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/spend'), [EprocSpendController::class, 'index'], $eprocMw);
$router->post($app('eproc/spend'), [EprocSpendController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/portal'), [EprocPortalController::class, 'index'], rateb_erp_mw('procurement', 'procurement.portal', 'procurement'));
$router->post($app('eproc/portal'), [EprocPortalController::class, 'store'], rateb_erp_mw('procurement', 'procurement.portal', 'procurement'));
$router->get($app('eproc/collaboration'), [EprocCollaborationController::class, 'index'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/collaboration'), [EprocCollaborationController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/rfq-templates'), [EprocRfqTemplatesController::class, 'index'], $eprocMw);
$router->post($app('eproc/rfq-templates'), [EprocRfqTemplatesController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/reports'), [EprocReportsController::class, 'index'], $eprocMw);
$router->get($app('eproc/qualification'), [EprocQualificationController::class, 'index'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/qualification'), [EprocQualificationController::class, 'store'], rateb_erp_mw('procurement', 'procurement.create', 'procurement'));
$router->get($app('eproc/suppliers/{id}'), [EprocSuppliersController::class, 'show'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/suppliers/{id}/transition'), [EprocSuppliersController::class, 'transition'], rateb_erp_mw('procurement', 'procurement.submit', 'procurement'));
$router->get($app('eproc/tenders/{id}'), [EprocTendersController::class, 'show'], rateb_erp_mw('procurement', 'procurement.tender', 'procurement'));
$router->post($app('eproc/tenders/{id}/transition'), [EprocTendersController::class, 'transition'], rateb_erp_mw('procurement', 'procurement.submit', 'procurement'));
$router->get($app('eproc/contracts/{id}'), [EprocContractsController::class, 'show'], rateb_erp_mw('procurement', 'procurement.contract', 'procurement'));
$router->post($app('eproc/contracts/{id}/transition'), [EprocContractsController::class, 'transition'], rateb_erp_mw('procurement', 'procurement.submit', 'procurement'));
$router->get($app('eproc/collaboration/{id}'), [EprocCollaborationController::class, 'show'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/collaboration/{id}/transition'), [EprocCollaborationController::class, 'transition'], rateb_erp_mw('procurement', 'procurement.submit', 'procurement'));
$router->get($app('eproc/qualification/{id}'), [EprocQualificationController::class, 'show'], rateb_erp_mw('procurement', 'procurement.supplier', 'procurement'));
$router->post($app('eproc/qualification/{id}/transition'), [EprocQualificationController::class, 'transition'], rateb_erp_mw('procurement', 'procurement.submit', 'procurement'));
}

/** Phase 22A — Enterprise Manufacturing (MRP) Platform ONLINE (mfg/*; additive; Offline deferred to 22B). */
$mfgMw = rateb_erp_mw('manufacturing', 'manufacturing.view', 'manufacturing');
$router->get($app('mfg'), [MfgDashboardController::class, 'index'], $mfgMw);
$router->get($app('mfg/products'), [MfgProductsController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.view', 'manufacturing'));
$router->get($app('mfg/products/create'), [MfgProductsController::class, 'create'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->post($app('mfg/products'), [MfgProductsController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/products/{id}'), [MfgProductsController::class, 'show'], rateb_erp_mw('manufacturing', 'manufacturing.view', 'manufacturing'));
$router->post($app('mfg/products/{id}/transition'), [MfgProductsController::class, 'transition'], rateb_erp_mw('manufacturing', 'manufacturing.submit', 'manufacturing'));
$router->get($app('mfg/boms'), [MfgBomsController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.bom', 'manufacturing'));
$router->get($app('mfg/boms/create'), [MfgBomsController::class, 'create'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->post($app('mfg/boms'), [MfgBomsController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/boms/{id}'), [MfgBomsController::class, 'show'], rateb_erp_mw('manufacturing', 'manufacturing.bom', 'manufacturing'));
$router->post($app('mfg/boms/{id}/transition'), [MfgBomsController::class, 'transition'], rateb_erp_mw('manufacturing', 'manufacturing.submit', 'manufacturing'));
$router->get($app('mfg/production-orders'), [MfgProductionOrdersController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.shopfloor', 'manufacturing'));
$router->get($app('mfg/production-orders/create'), [MfgProductionOrdersController::class, 'create'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->post($app('mfg/production-orders'), [MfgProductionOrdersController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/production-orders/{id}'), [MfgProductionOrdersController::class, 'show'], rateb_erp_mw('manufacturing', 'manufacturing.shopfloor', 'manufacturing'));
$router->post($app('mfg/production-orders/{id}/transition'), [MfgProductionOrdersController::class, 'transition'], rateb_erp_mw('manufacturing', 'manufacturing.submit', 'manufacturing'));
$router->get($app('mfg/work-orders'), [MfgWorkOrdersController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.shopfloor', 'manufacturing'));
$router->get($app('mfg/work-orders/create'), [MfgWorkOrdersController::class, 'create'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->post($app('mfg/work-orders'), [MfgWorkOrdersController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/work-orders/{id}'), [MfgWorkOrdersController::class, 'show'], rateb_erp_mw('manufacturing', 'manufacturing.shopfloor', 'manufacturing'));
$router->post($app('mfg/work-orders/{id}/transition'), [MfgWorkOrdersController::class, 'transition'], rateb_erp_mw('manufacturing', 'manufacturing.submit', 'manufacturing'));
$router->get($app('mfg/work-centers'), [MfgWorkCentersController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.planning', 'manufacturing'));
$router->post($app('mfg/work-centers'), [MfgWorkCentersController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/routings'), [MfgRoutingsController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.bom', 'manufacturing'));
$router->post($app('mfg/routings'), [MfgRoutingsController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/routings/{id}'), [MfgRoutingsController::class, 'show'], rateb_erp_mw('manufacturing', 'manufacturing.bom', 'manufacturing'));
$router->get($app('mfg/capacity'), [MfgCapacityController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.planning', 'manufacturing'));
$router->post($app('mfg/capacity'), [MfgCapacityController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/calendar'), [MfgCalendarController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.planning', 'manufacturing'));
$router->post($app('mfg/calendar'), [MfgCalendarController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/schedules'), [MfgSchedulesController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.planning', 'manufacturing'));
$router->post($app('mfg/schedules'), [MfgSchedulesController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/quality'), [MfgQualityController::class, 'index'], rateb_erp_mw('manufacturing', 'manufacturing.quality', 'manufacturing'));
$router->post($app('mfg/quality'), [MfgQualityController::class, 'store'], rateb_erp_mw('manufacturing', 'manufacturing.create', 'manufacturing'));
$router->get($app('mfg/reports'), [MfgReportsController::class, 'index'], $mfgMw);

/** Phase 23A — Enterprise HR Platform ONLINE (hrm/*; additive; Offline deferred to 23B). */
$hrmMw = rateb_erp_mw('hr', 'hr.view', 'hr');
$router->get($app('hrm'), [HrmDashboardController::class, 'index'], $hrmMw);
$router->get($app('hrm/dashboard'), [HrmDashboardController::class, 'index'], $hrmMw);
$router->get($app('hrm/employees'), [HrmEmployeesController::class, 'index'], rateb_erp_mw('hr', 'hr.view', 'hr'));
$router->get($app('hrm/employees/create'), [HrmEmployeesController::class, 'create'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->post($app('hrm/employees'), [HrmEmployeesController::class, 'store'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->get($app('hrm/employees/{id}'), [HrmEmployeesController::class, 'show'], rateb_erp_mw('hr', 'hr.view', 'hr'));
$router->post($app('hrm/employees/{id}/transition'), [HrmEmployeesController::class, 'transition'], rateb_erp_mw('hr', 'hr.update', 'hr'));
$router->get($app('hrm/departments'), [HrmDepartmentsController::class, 'index'], rateb_erp_mw('hr', 'hr.view', 'hr'));
$router->post($app('hrm/departments'), [HrmDepartmentsController::class, 'store'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->get($app('hrm/positions'), [HrmPositionsController::class, 'index'], rateb_erp_mw('hr', 'hr.view', 'hr'));
$router->post($app('hrm/positions'), [HrmPositionsController::class, 'store'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->get($app('hrm/organization'), [HrmOrganizationController::class, 'index'], rateb_erp_mw('hr', 'hr.view', 'hr'));
$router->post($app('hrm/organization/units'), [HrmOrganizationController::class, 'storeUnit'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->post($app('hrm/organization/locations'), [HrmOrganizationController::class, 'storeLocation'], rateb_erp_mw('hr', 'hr.create', 'hr'));
$router->get($app('hrm/training'), [HrmTrainingController::class, 'index'], rateb_erp_mw('hr', 'hr.training', 'hr'));
$router->get($app('hrm/training/create'), [HrmTrainingController::class, 'create'], rateb_erp_mw('hr', 'hr.training', 'hr'));
$router->post($app('hrm/training'), [HrmTrainingController::class, 'store'], rateb_erp_mw('hr', 'hr.training', 'hr'));
$router->get($app('hrm/training/{id}'), [HrmTrainingController::class, 'show'], rateb_erp_mw('hr', 'hr.training', 'hr'));
$router->post($app('hrm/training/{id}/transition'), [HrmTrainingController::class, 'transition'], rateb_erp_mw('hr', 'hr.training', 'hr'));
$router->get($app('hrm/performance'), [HrmPerformanceController::class, 'index'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->get($app('hrm/performance/create'), [HrmPerformanceController::class, 'create'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->post($app('hrm/performance'), [HrmPerformanceController::class, 'store'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->get($app('hrm/performance/{id}'), [HrmPerformanceController::class, 'show'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->post($app('hrm/performance/{id}/transition'), [HrmPerformanceController::class, 'transition'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->get($app('hrm/promotions'), [HrmPromotionsController::class, 'index'], rateb_erp_mw('hr', 'hr.promotions', 'hr'));
$router->post($app('hrm/promotions'), [HrmPromotionsController::class, 'store'], rateb_erp_mw('hr', 'hr.promotions', 'hr'));
$router->get($app('hrm/transfers'), [HrmTransfersController::class, 'index'], rateb_erp_mw('hr', 'hr.transfers', 'hr'));
$router->post($app('hrm/transfers'), [HrmTransfersController::class, 'store'], rateb_erp_mw('hr', 'hr.transfers', 'hr'));
$router->get($app('hrm/goals'), [HrmGoalsController::class, 'index'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->post($app('hrm/goals'), [HrmGoalsController::class, 'store'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->get($app('hrm/competencies'), [HrmCompetenciesController::class, 'index'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->post($app('hrm/competencies'), [HrmCompetenciesController::class, 'store'], rateb_erp_mw('hr', 'hr.performance', 'hr'));
$router->get($app('hrm/reports'), [HrmReportsController::class, 'index'], $hrmMw);
$router->get($app('hrm/timeline'), [HrmTimelineController::class, 'index'], $hrmMw);

/** Phase 24A — Enterprise Payroll Platform ONLINE (payroll/*; additive; Offline deferred to 24B). */
$payrollMw = rateb_erp_mw('payroll', 'payroll.view', 'payroll');
$router->get($app('payroll-platform'), [PayrollPlatformController::class, 'index'], $payrollMw);
$router->get($app('payroll'), [PayrollDashboardController::class, 'index'], $payrollMw);
$router->get($app('payroll/dashboard'), [PayrollDashboardController::class, 'index'], $payrollMw);
$router->get($app('payroll/cycles'), [PayrollCyclesController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/cycles'), [PayrollCyclesController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/batches'), [PayrollBatchesController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/batches'), [PayrollBatchesController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/batches/{id}'), [PayrollBatchesController::class, 'show'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/batches/{id}/calculate'), [PayrollBatchesController::class, 'calculate'], rateb_erp_mw('payroll', 'payroll.calculate', 'payroll'));
$router->post($app('payroll/batches/{id}/transition'), [PayrollBatchesController::class, 'transition'], rateb_erp_mw('payroll', 'payroll.review', 'payroll'));
$router->get($app('payroll/payslips'), [PayrollPayslipsController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->get($app('payroll/loans'), [PayrollLoansController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/loans'), [PayrollLoansController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/advances'), [PayrollAdvancesController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/advances'), [PayrollAdvancesController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/overtime'), [PayrollOvertimeController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/overtime'), [PayrollOvertimeController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/salary-structures'), [PayrollSalaryStructuresController::class, 'index'], rateb_erp_mw('payroll', 'payroll.view', 'payroll'));
$router->post($app('payroll/salary-structures'), [PayrollSalaryStructuresController::class, 'store'], rateb_erp_mw('payroll', 'payroll.create', 'payroll'));
$router->get($app('payroll/reports'), [PayrollReportsController::class, 'index'], $payrollMw);
$router->get($app('payroll/timeline'), [PayrollTimelinePageController::class, 'index'], $payrollMw);

/** Phase 25A — Enterprise Quality Management Platform ONLINE (qms/*; additive; Offline deferred to 25B). */
$qmsMw = rateb_erp_mw('quality', 'quality.view', 'quality');
$router->get($app('qms-platform'), [QualityPlatformController::class, 'index'], $qmsMw);
$router->get($app('qms'), [QualityDashboardController::class, 'index'], $qmsMw);
$router->get($app('qms/dashboard'), [QualityDashboardController::class, 'index'], $qmsMw);
$router->get($app('qms/plans'), [QualityPlansController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/plans'), [QualityPlansController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/standards'), [QualityStandardsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/standards'), [QualityStandardsController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/checklists'), [QualityChecklistsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/checklists'), [QualityChecklistsController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/inspections'), [QualityInspectionsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/inspections'), [QualityInspectionsController::class, 'store'], rateb_erp_mw('quality', 'quality.inspect', 'quality'));
$router->get($app('qms/inspections/{id}'), [QualityInspectionsController::class, 'show'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/inspections/{id}/transition'), [QualityInspectionsController::class, 'transition'], rateb_erp_mw('quality', 'quality.inspect', 'quality'));
$router->get($app('qms/defects'), [QualityDefectsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/defects'), [QualityDefectsController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/nonconformities'), [QualityNonconformitiesController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/nonconformities'), [QualityNonconformitiesController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/corrective-actions'), [QualityCorrectiveActionsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/corrective-actions'), [QualityCorrectiveActionsController::class, 'store'], rateb_erp_mw('quality', 'quality.corrective', 'quality'));
$router->get($app('qms/corrective-actions/{id}'), [QualityCorrectiveActionsController::class, 'show'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/corrective-actions/{id}/transition'), [QualityCorrectiveActionsController::class, 'transition'], rateb_erp_mw('quality', 'quality.corrective', 'quality'));
$router->get($app('qms/preventive-actions'), [QualityPreventiveActionsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/preventive-actions'), [QualityPreventiveActionsController::class, 'store'], rateb_erp_mw('quality', 'quality.preventive', 'quality'));
$router->get($app('qms/audits'), [QualityAuditsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/audits'), [QualityAuditsController::class, 'store'], rateb_erp_mw('quality', 'quality.audit', 'quality'));
$router->get($app('qms/complaints'), [QualityComplaintsController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/complaints'), [QualityComplaintsController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/supplier-quality'), [QualitySupplierQualityController::class, 'index'], rateb_erp_mw('quality', 'quality.view', 'quality'));
$router->post($app('qms/supplier-quality'), [QualitySupplierQualityController::class, 'store'], rateb_erp_mw('quality', 'quality.create', 'quality'));
$router->get($app('qms/reports'), [QualityReportsController::class, 'index'], $qmsMw);
$router->get($app('qms/timeline'), [QualityTimelinePageController::class, 'index'], $qmsMw);

/** Phase 26A — Enterprise Document Management Platform ONLINE (dms/*; additive; Offline deferred to 26B). */
$dmsMw = rateb_erp_mw('documents', 'documents.view', 'documents');
$router->get($app('dms-platform'), [DocumentManagementPlatformController::class, 'index'], $dmsMw);
$router->get($app('dms'), [DmsDashboardController::class, 'index'], $dmsMw);
$router->get($app('dms/dashboard'), [DmsDashboardController::class, 'index'], $dmsMw);
$router->get($app('dms/repositories'), [DmsRepositoriesController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->post($app('dms/repositories'), [DmsRepositoriesController::class, 'store'], rateb_erp_mw('documents', 'documents.create', 'documents'));
$router->get($app('dms/folders'), [DmsFoldersController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->post($app('dms/folders'), [DmsFoldersController::class, 'store'], rateb_erp_mw('documents', 'documents.create', 'documents'));
$router->get($app('dms/documents'), [DmsDocumentsController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->post($app('dms/documents'), [DmsDocumentsController::class, 'store'], rateb_erp_mw('documents', 'documents.create', 'documents'));
$router->get($app('dms/documents/{id}'), [DmsDocumentsController::class, 'show'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->post($app('dms/documents/{id}/transition'), [DmsDocumentsController::class, 'transition'], rateb_erp_mw('documents', 'documents.update', 'documents'));
$router->get($app('dms/versions'), [DmsVersionsController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->get($app('dms/search'), [DmsSearchController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->get($app('dms/favorites'), [DmsFavoritesController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->get($app('dms/shares'), [DmsSharesController::class, 'index'], rateb_erp_mw('documents', 'documents.view', 'documents'));
$router->post($app('dms/shares'), [DmsSharesController::class, 'store'], rateb_erp_mw('documents', 'documents.share', 'documents'));
$router->get($app('dms/retention'), [DmsRetentionController::class, 'index'], rateb_erp_mw('documents', 'documents.retention', 'documents'));
$router->post($app('dms/retention'), [DmsRetentionController::class, 'store'], rateb_erp_mw('documents', 'documents.retention', 'documents'));
$router->get($app('dms/legal-holds'), [DmsLegalHoldsController::class, 'index'], rateb_erp_mw('documents', 'documents.retention', 'documents'));
$router->post($app('dms/legal-holds'), [DmsLegalHoldsController::class, 'store'], rateb_erp_mw('documents', 'documents.retention', 'documents'));
$router->get($app('dms/permissions'), [DmsPermissionsController::class, 'index'], rateb_erp_mw('documents', 'documents.admin', 'documents'));
$router->post($app('dms/permissions'), [DmsPermissionsController::class, 'store'], rateb_erp_mw('documents', 'documents.admin', 'documents'));
$router->get($app('dms/timeline'), [DmsTimelinePageController::class, 'index'], $dmsMw);
$router->get($app('dms/reports'), [DmsReportsController::class, 'index'], $dmsMw);

/** Phase 27A — Enterprise Business Intelligence & Analytics Platform ONLINE (bi/*; additive; Offline deferred to 27B). */
$biMw = rateb_erp_mw('bi', 'bi.view', 'bi');
$router->get($app('bi-platform'), [BiPlatformController::class, 'index'], $biMw);
$router->get($app('bi'), [BiDashboardController::class, 'index'], $biMw);
$router->get($app('bi/dashboard'), [BiDashboardController::class, 'index'], $biMw);
$router->get($app('bi/dashboards'), [BiDashboardsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/dashboards'), [BiDashboardsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/dashboards/{id}'), [BiDashboardsController::class, 'show'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/dashboards/{id}/transition'), [BiDashboardsController::class, 'transition'], rateb_erp_mw('bi', 'bi.publish', 'bi'));
$router->get($app('bi/kpis'), [BiKpisController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/kpis'), [BiKpisController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/kpis/{id}'), [BiKpisController::class, 'show'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/kpis/{id}/transition'), [BiKpisController::class, 'transition'], rateb_erp_mw('bi', 'bi.publish', 'bi'));
$router->get($app('bi/reports'), [BiReportsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/reports'), [BiReportsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/reports/{id}'), [BiReportsController::class, 'show'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/reports/{id}/transition'), [BiReportsController::class, 'transition'], rateb_erp_mw('bi', 'bi.publish', 'bi'));
$router->get($app('bi/widgets'), [BiWidgetsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/widgets'), [BiWidgetsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/datasets'), [BiDatasetsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/datasets'), [BiDatasetsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/alerts'), [BiAlertsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/alerts'), [BiAlertsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/schedules'), [BiSchedulesController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/schedules'), [BiSchedulesController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/exports'), [BiExportsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/exports'), [BiExportsController::class, 'store'], rateb_erp_mw('bi', 'bi.export', 'bi'));
$router->get($app('bi/trends'), [BiTrendsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/trends'), [BiTrendsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/forecasts'), [BiForecastsController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/forecasts'), [BiForecastsController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/scopes'), [BiScopesController::class, 'index'], rateb_erp_mw('bi', 'bi.view', 'bi'));
$router->post($app('bi/scopes'), [BiScopesController::class, 'store'], rateb_erp_mw('bi', 'bi.create', 'bi'));
$router->get($app('bi/analytics'), [BiAnalyticsController::class, 'index'], $biMw);
$router->get($app('bi/timeline'), [BiTimelinePageController::class, 'index'], $biMw);

$hrAttMw = rateb_erp_mw('hr', '', 'hr-attendance');
$router->get($app('hr/attendance/bulk'), [HrAttendanceBulkController::class, 'index'], $hrAttMw);
$router->post($app('hr/attendance/bulk'), [HrAttendanceBulkController::class, 'store'], $hrAttMw);

$hrCrudRoutes = [
    'hr/employees' => ['class' => HrEmployeesController::class, 'entity' => 'hr-employees'],
    'hr/departments' => ['class' => HrDepartmentsController::class, 'entity' => 'hr-employees'],
    'hr/job-titles' => ['class' => HrJobTitlesController::class, 'entity' => 'hr-employees'],
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
$router->get($app('hr/letters'), [HrLettersController::class, 'index'], $hrLeaveMw);
$router->post($app('hr/letters/{id}/issue'), [HrLettersController::class, 'issue'], $hrLeaveMw);
$router->get($app('hr/letters/{id}/download'), [HrLettersController::class, 'download'], $hrLeaveMw);
$hrEmpMwDec = rateb_erp_mw('hr', '', 'hr-employees');
$router->get($app('hr/decisions'), [HrDecisionsController::class, 'index'], $hrEmpMwDec);
$router->get($app('hr/decisions/create'), [HrDecisionsController::class, 'create'], $hrEmpMwDec);
$router->post($app('hr/decisions'), [HrDecisionsController::class, 'store'], $hrEmpMwDec);
$router->post($app('hr/decisions/{id}/execute'), [HrDecisionsController::class, 'execute'], $hrEmpMwDec);
$router->get($app('hr/disciplinary'), [HrDisciplinaryController::class, 'index'], $hrEmpMwDec);
$router->get($app('hr/disciplinary/create'), [HrDisciplinaryController::class, 'create'], $hrEmpMwDec);
$router->post($app('hr/disciplinary'), [HrDisciplinaryController::class, 'store'], $hrEmpMwDec);

foreach ($hrCrudRoutes as $path => $cfg) {
    $class = $cfg['class'];
    $mw = rateb_erp_mw('hr', '', $cfg['entity']);
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    if ($path === 'hr/employees') {
        $router->get($app('hr/employees/export'), [HrEmployeesController::class, 'export'], $mw);
        $router->get($app('hr/employees/{id}/360-tab'), [HrEmployeesController::class, 'show360Tab'], $mw);
        $router->get($app('hr/employees/{id}'), [HrEmployeesController::class, 'show'], $mw);
    } else {
        $router->get($app($path . '/export'), [$class, 'export'], rateb_erp_mw('hr', 'reports.export', $cfg['entity']));
    }
    if ($path === 'hr/fleet') {
        $router->get($app('hr/fleet/{id}'), [HrFleetController::class, 'show'], $mw);
        $router->get($app('hr/fleet/{id}/print'), [HrFleetController::class, 'print'], $mw);
        $router->get($app('hr/fleet/{id}/receipt'), [HrFleetController::class, 'employeeReceipt'], $mw);
    }
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
    $router->get($app($path . '/{id}/documents/panel'), [$class, 'documentsPanel'], $mw);
    $router->get($app($path . '/{id}/documents'), [$class, 'documents'], $mw);
    $router->post($app($path . '/{id}/documents'), [$class, 'storeDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}'), [$class, 'updateDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}/delete'), [$class, 'destroyDocument'], $mw);
}

$hrLeaveMw = rateb_erp_mw('hr', '', 'hr-leaves');
$router->post($app('hr/leaves/{id}/approve'), $blockCompanyApprovalAction, $hrLeaveMw);
$router->post($app('hr/leaves/{id}/reject'), $blockCompanyApprovalAction, $hrLeaveMw);
$router->post($app('hr/leaves/{id}/cancel'), [HrLeavesController::class, 'cancel'], $hrLeaveMw);
$router->post($app('hr/permission-requests/{id}/approve'), $blockCompanyApprovalAction, $hrAttMw);
$router->post($app('hr/permission-requests/{id}/reject'), $blockCompanyApprovalAction, $hrAttMw);
$router->post($app('hr/requests/{id}/approve'), $blockCompanyApprovalAction, $hrLeaveMw);
$router->post($app('hr/requests/{id}/reject'), $blockCompanyApprovalAction, $hrLeaveMw);

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
$router->post($app('hr/payroll/{id}/approve'), $blockCompanyApprovalAction, $hrPayMw);
$router->post($app('hr/payroll/{id}/post'), [HrPayrollController::class, 'post'], $hrPayMw);
$router->get($app('hr/payroll/{id}/export'), [HrPayrollController::class, 'exportPeriod'], $hrPayMw);
$router->get($app('hr/payroll/{id}/payslip/{lineId}'), [HrPayrollController::class, 'payslip'], $hrPayMw);

$hrReportsMw = rateb_erp_mw('hr', '', 'hr');
$router->get($app('hr/reports/leaves'), [HrReportsController::class, 'leaves'], $hrReportsMw);
$router->get($app('hr/reports/leaves/export'), [HrReportsController::class, 'leavesExport'], rateb_erp_mw('hr', 'reports.export', 'hr'));
$router->get($app('hr/reports'), [HrReportsController::class, 'index'], $hrReportsMw);
$router->get($app('hr/reports/export'), [HrReportsController::class, 'export'], rateb_erp_mw('hr', 'reports.export', 'hr'));
$router->get($app('accounting'), [CompanyAccountingDashboardController::class, 'index'], rateb_erp_mw('accounting', '', 'accounting'));
$router->get($app('accounting/platform'), [AccountingPlatformHubController::class, 'index'], rateb_erp_mw('accounting', 'accounting.view', 'accounting'));
$router->get($app('accounting/currencies'), [AccountingCurrenciesController::class, 'index'], rateb_erp_mw('accounting', 'accounting.view', 'accounting'));
$router->get($app('accounting/currencies/create'), [AccountingCurrenciesController::class, 'create'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/currencies'), [AccountingCurrenciesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/exchange-rates'), [AccountingExchangeRatesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->get($app('accounting/tax-codes'), [AccountingTaxCodesController::class, 'index'], rateb_erp_mw('accounting', 'accounting.view', 'accounting'));
$router->get($app('accounting/tax-codes/create'), [AccountingTaxCodesController::class, 'create'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/tax-codes'), [AccountingTaxCodesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->get($app('accounting/profit-centers'), [AccountingProfitCentersController::class, 'index'], rateb_erp_mw('accounting', 'accounting.view', 'accounting'));
$router->get($app('accounting/profit-centers/create'), [AccountingProfitCentersController::class, 'create'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/profit-centers'), [AccountingProfitCentersController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->get($app('accounting/recurring'), [AccountingRecurringController::class, 'index'], rateb_erp_mw('accounting', 'accounting.view', 'accounting'));
$router->get($app('accounting/recurring/create'), [AccountingRecurringController::class, 'create'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/recurring'), [AccountingRecurringController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/recurring/{id}/generate'), [AccountingRecurringController::class, 'generate'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->get($app('accounting/opening-balances/create'), [AccountingOpeningBalancesController::class, 'create'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('accounting/opening-balances'), [AccountingOpeningBalancesController::class, 'store'], rateb_erp_mw('accounting', 'accounting.create', 'accounting'));
$router->post($app('journal-entries/{id}/lifecycle'), [AccountingWorkflowController::class, 'transition'], rateb_erp_mw('accounting', 'accounting.update', 'journal-entries'));
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
$router->post($app('journal-entries/bulk-approve'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-reject'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-void'), [CompanyJournalEntriesController::class, 'bulkVoid'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/bulk-delete'), [CompanyJournalEntriesController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->get($app('journal-entries/{id}/edit'), [CompanyJournalEntriesController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}/submit-approval'), [CompanyJournalEntriesController::class, 'submitForApproval'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->post($app('journal-entries/{id}/post'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/reject'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/void'), [CompanyJournalEntriesController::class, 'voidEntry'], rateb_erp_mw('accounting', 'accounting.approve', 'journal-entries'));
$router->post($app('journal-entries/{id}/delete'), [CompanyJournalEntriesController::class, 'destroy'], rateb_erp_mw('accounting', 'accounting.manage', 'journal-entries'));
$router->get($app('journal-entries/{id}'), [CompanyJournalEntriesController::class, 'show'], rateb_erp_mw('accounting', '', 'journal-entries'));

$router->get($app('accounting/voucher-approval'), $redirectApprovalsOversight, rateb_erp_mw('accounting', '', 'voucher-approval'));
$router->get($app('cash-vouchers'), [CompanyCashVouchersController::class, 'index'], rateb_erp_mw('accounting', '', 'cash-vouchers'));
$router->get($app('cash-vouchers/create'), [CompanyCashVouchersController::class, 'create'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers'), [CompanyCashVouchersController::class, 'store'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-approve'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-reject'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-void'), [CompanyCashVouchersController::class, 'bulkVoid'], rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/bulk-delete'), [CompanyCashVouchersController::class, 'bulkDestroy'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->get($app('cash-vouchers/{id}/edit'), [CompanyCashVouchersController::class, 'edit'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}'), [CompanyCashVouchersController::class, 'update'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->get($app('cash-vouchers/{id}'), [CompanyCashVouchersController::class, 'show'], rateb_erp_mw('accounting', '', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/submit-approval'), [CompanyCashVouchersController::class, 'submitForApproval'], rateb_erp_mw('accounting', 'accounting.manage', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/post'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
$router->post($app('cash-vouchers/{id}/reject'), $blockCompanyApprovalAction, rateb_erp_mw('accounting', 'accounting.approve', 'cash-vouchers'));
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

$companyCrudExports = [
    ['chart-of-accounts', CompanyChartOfAccountsController::class, 'chart-of-accounts'],
    ['journal-entries', CompanyJournalEntriesController::class, 'journal-entries'],
    ['cash-vouchers', CompanyCashVouchersController::class, 'cash-vouchers'],
    ['fiscal-periods', CompanyFiscalPeriodsController::class, 'fiscal-periods'],
    ['bank-accounts', CompanyBankAccountsController::class, 'bank-accounts'],
    ['cost-centers', CompanyCostCentersController::class, 'cost-centers'],
    ['customers', CompanyCustomersController::class, 'customers'],
];
foreach ($companyCrudExports as [$exportPath, $exportClass, $exportResource]) {
    $router->get($app($exportPath . '/export'), [$exportClass, 'export'], rateb_erp_mw('accounting', 'reports.export', $exportResource));
}

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
$router->post($app('warehouse-transfers/{id}/approve'), $blockCompanyApprovalAction, $wtMw);
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
$router->get($app('supplier-classifications/export'), [SupplierClassificationsController::class, 'export'], rateb_erp_mw('suppliers', 'reports.export', 'supplier-classifications'));

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
$router->get($app('supplier-comms/export'), [\Rateb\App\Controllers\Company\SupplierCommsController::class, 'export'], rateb_erp_mw('suppliers', 'reports.export', 'supplier-comms'));
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
$router->post($app('contract-renewals/{id}/approve'), $blockCompanyApprovalAction, $ctrWriteMw);
$router->post($app('contract-renewals/{id}/reject'), $blockCompanyApprovalAction, $ctrWriteMw);
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
$router->post($app('asset-maintenance/{id}/approve'), $blockCompanyApprovalAction, $astWriteMw);
$router->post($app('asset-maintenance/{id}/reject'), $blockCompanyApprovalAction, $astWriteMw);
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
$router->post($app('asset-assignments/{id}/approve'), $blockCompanyApprovalAction, $aaWriteMw);
$router->post($app('asset-assignments/{id}/reject'), $blockCompanyApprovalAction, $aaWriteMw);
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
$router->post($app('asset-depreciation/{id}/approve'), $blockCompanyApprovalAction, $adWriteMw);
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
$router->post($app('device-maintenance/{id}/approve'), $blockCompanyApprovalAction, $devWriteMw);
$router->post($app('device-maintenance/{id}/reject'), $blockCompanyApprovalAction, $devWriteMw);
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
$router->post($app('device-spare-parts/{id}/approve'), $blockCompanyApprovalAction, $dspWriteMw);
$router->post($app('device-spare-parts/{id}/reject'), $blockCompanyApprovalAction, $dspWriteMw);
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

$branchDashMw = rateb_erp_mw('', 'branch.dashboard.view');
$branchCompareMw = rateb_erp_mw('', 'branch.dashboard.compare');
$branchReportsMw = rateb_erp_mw('', 'branch.reports.view');
$branchTransfersMw = rateb_erp_mw('', 'branch.transfers.view');
$branchTransfersWriteMw = rateb_erp_mw('', 'branch.transfers.manage');
$router->get($app('branch-dashboard'), [BranchDashboardController::class, 'index'], $branchDashMw);
$router->get($app('branch-dashboard/compare'), [BranchDashboardController::class, 'compare'], $branchCompareMw);
$router->get($app('branch-dashboard/reports'), [BranchDashboardController::class, 'reports'], $branchReportsMw);
$router->get($app('branch-transfers'), [InterBranchTransfersController::class, 'index'], $branchTransfersMw);
$router->get($app('branch-transfers/create'), [InterBranchTransfersController::class, 'create'], $branchTransfersWriteMw);
$router->post($app('branch-transfers'), [InterBranchTransfersController::class, 'store'], $branchTransfersWriteMw);
$router->post($app('branch-transfers/{id}/approve'), $blockCompanyApprovalAction, $branchTransfersWriteMw);

$branchFinPlMw = rateb_erp_mw('accounting', 'branch.financial.pl');
$branchFinBsMw = rateb_erp_mw('accounting', 'branch.financial.bs');
$branchFinCfMw = rateb_erp_mw('accounting', 'branch.financial.cf');
$branchFinConMw = rateb_erp_mw('accounting', 'branch.financial.consolidated');
$branchFinConTbMw = rateb_erp_mw('accounting', 'branch.financial.consolidated.tb');
$branchFinConGlMw = rateb_erp_mw('accounting', 'branch.financial.consolidated.gl');
$branchFinArMw = rateb_erp_mw('accounting', 'branch.financial.araging');
$branchFinApMw = rateb_erp_mw('accounting', 'branch.financial.apaging');
$branchFinRecMw = rateb_erp_mw('accounting', 'branch.financial.receivables');
$branchFinPayMw = rateb_erp_mw('accounting', 'branch.financial.payables');
$router->get($app('branch-financial'), [BranchFinancialReportsController::class, 'index'], $branchFinPlMw);
$router->get($app('branch-financial/profit-loss'), [BranchFinancialReportsController::class, 'profitLoss'], $branchFinPlMw);
$router->get($app('branch-financial/balance-sheet'), [BranchFinancialReportsController::class, 'balanceSheet'], $branchFinBsMw);
$router->get($app('branch-financial/cash-flow'), [BranchFinancialReportsController::class, 'cashFlow'], $branchFinCfMw);
$router->get($app('branch-financial/consolidated'), [BranchFinancialReportsController::class, 'consolidated'], $branchFinConMw);
$router->get($app('branch-financial/consolidated-trial-balance'), [BranchFinancialReportsController::class, 'consolidatedTrialBalance'], $branchFinConTbMw);
$router->get($app('branch-financial/consolidated-general-ledger'), [BranchFinancialReportsController::class, 'consolidatedGeneralLedger'], $branchFinConGlMw);
$router->get($app('branch-financial/ar-aging'), [BranchFinancialReportsController::class, 'branchArAging'], $branchFinArMw);
$router->get($app('branch-financial/ap-aging'), [BranchFinancialReportsController::class, 'branchApAging'], $branchFinApMw);
$router->get($app('branch-financial/receivables'), [BranchFinancialReportsController::class, 'branchReceivables'], $branchFinRecMw);
$router->get($app('branch-financial/payables'), [BranchFinancialReportsController::class, 'branchPayables'], $branchFinPayMw);

// Phase 6 — Enterprise Accounting Control Center (UI layer; consumes app/Accounting services)
$accCtrlDashMw = rateb_erp_mw('accounting', 'accounting.dashboard', 'accounting-control');
$accCtrlEventsMw = rateb_erp_mw('accounting', 'accounting.events');
$accCtrlReplayMw = rateb_erp_mw('accounting', 'accounting.replay');
$accCtrlAuditMw = rateb_erp_mw('accounting', 'accounting.audit');
$accCtrlProjMw = rateb_erp_mw('accounting', 'accounting.projections');
$accCtrlConsMw = rateb_erp_mw('accounting', 'accounting.consolidation');
$accCtrlDriftMw = rateb_erp_mw('accounting', 'accounting.drift');
$accCtrlReconMw = rateb_erp_mw('accounting', 'accounting.reconciliation');
$accCtrlIntMw = rateb_erp_mw('accounting', 'accounting.integrity');
$accCtrlHealthMw = rateb_erp_mw('accounting', 'accounting.system_health');
// API: module gate only — granular permission enforced inside AccountingControlController::api()
$accCtrlApiMw = rateb_erp_mw('accounting');

$router->get($app('accounting-control'), [AccountingControlController::class, 'dashboard'], $accCtrlDashMw);
$router->get($app('accounting-control/events'), [AccountingControlController::class, 'events'], $accCtrlEventsMw);
$router->get($app('accounting-control/replay'), [AccountingControlController::class, 'replay'], $accCtrlReplayMw);
$router->get($app('accounting-control/audit'), [AccountingControlController::class, 'audit'], $accCtrlAuditMw);
$router->get($app('accounting-control/projections'), [AccountingControlController::class, 'projections'], $accCtrlProjMw);
$router->get($app('accounting-control/consolidation'), [AccountingControlController::class, 'consolidation'], $accCtrlConsMw);
$router->get($app('accounting-control/drift'), [AccountingControlController::class, 'drift'], $accCtrlDriftMw);
$router->get($app('accounting-control/reconciliation'), [AccountingControlController::class, 'reconciliation'], $accCtrlReconMw);
$router->get($app('accounting-control/integrity'), [AccountingControlController::class, 'integrity'], $accCtrlIntMw);
$router->get($app('accounting-control/settings'), [AccountingControlController::class, 'settings'], $accCtrlDashMw);
$router->get($app('accounting-control/health'), [AccountingControlController::class, 'health'], $accCtrlHealthMw);
$router->get($app('accounting-control/timeline'), [AccountingControlController::class, 'timeline'], $accCtrlDashMw);
$router->get($app('accounting-control/notifications'), [AccountingControlController::class, 'notifications'], $accCtrlDashMw);
$router->get($app('accounting-control/diagnostics'), [AccountingControlController::class, 'diagnostics'], $accCtrlHealthMw);
$router->get($app('accounting-control/api/{resource}'), [AccountingControlController::class, 'api'], $accCtrlApiMw);
$router->post($app('accounting-control/api/{resource}'), [AccountingControlController::class, 'api'], $accCtrlApiMw);

if (is_file(RATEB_ROOT . '/offline/server/routes/offline-web.php')) {
    require RATEB_ROOT . '/offline/server/routes/offline-web.php';
}

// Phase P2 — Offline device trust admin (company security)
$offlineDevicesViewMw = rateb_erp_mw('', 'offline.devices.view', 'security/offline-devices');
$offlineDevicesManageMw = rateb_erp_mw('', 'offline.devices.manage', 'security/offline-devices');
$router->get($app('security/offline-devices'), [OfflineDevicesController::class, 'index'], $offlineDevicesViewMw);
$router->post($app('security/offline-devices/revoke'), [OfflineDevicesController::class, 'revoke'], $offlineDevicesManageMw);
$router->post($app('security/offline-devices/rename'), [OfflineDevicesController::class, 'rename'], $offlineDevicesManageMw);
$router->post($app('security/offline-devices/force-logout'), [OfflineDevicesController::class, 'forceLogout'], $offlineDevicesManageMw);
$router->post($app('security/offline-devices/restore'), [OfflineDevicesController::class, 'restore'], $offlineDevicesManageMw);

// Phase WEBSITE-04 — Enterprise Website Builder (company-scoped)
$websiteMw = rateb_erp_mw('website', 'website.view', 'website');
$websiteBuilderMw = rateb_erp_mw('website', 'website.builder.manage', 'website-builder');
$websitePublishMw = rateb_erp_mw('website', 'website.publish', 'website');
$websiteThemeMw = rateb_erp_mw('website', 'website.theme.manage', 'website-theme');
$websiteMediaMw = rateb_erp_mw('website', 'website.media.manage', 'website-media');
$websiteFormsMw = rateb_erp_mw('website', 'website.forms.manage', 'website-forms');
$websitePagesMw = rateb_erp_mw('website', 'website.pages.manage', 'website');

$router->get($app('website'), [WebsiteDashboardController::class, 'index'], $websiteMw);
$router->get($app('website/pages'), [WebsitePagesController::class, 'index'], $websitePagesMw);
$router->get($app('website/pages/create'), [WebsitePagesController::class, 'create'], $websitePagesMw);
$router->post($app('website/pages'), [WebsitePagesController::class, 'store'], $websitePagesMw);
$router->get($app('website/pages/{id}/edit'), [WebsitePagesController::class, 'edit'], $websitePagesMw);
$router->post($app('website/pages/{id}'), [WebsitePagesController::class, 'update'], $websitePagesMw);
$router->post($app('website/pages/{id}/delete'), [WebsitePagesController::class, 'destroy'], $websitePagesMw);

$router->get($app('website/builder'), [WebsiteBuilderController::class, 'index'], $websiteBuilderMw);
$router->post($app('website/builder/reorder'), [WebsiteBuilderController::class, 'reorder'], $websiteBuilderMw);
$router->post($app('website/builder/section'), [WebsiteBuilderController::class, 'addSection'], $websiteBuilderMw);
$router->post($app('website/builder/section/delete'), [WebsiteBuilderController::class, 'deleteSection'], $websiteBuilderMw);
$router->post($app('website/builder/block'), [WebsiteBuilderController::class, 'addBlock'], $websiteBuilderMw);
$router->post($app('website/builder/block/update'), [WebsiteBuilderController::class, 'updateBlock'], $websiteBuilderMw);
$router->post($app('website/builder/block/delete'), [WebsiteBuilderController::class, 'deleteBlock'], $websiteBuilderMw);
$router->post($app('website/builder/draft'), [WebsiteBuilderController::class, 'saveDraft'], $websiteBuilderMw);
$router->post($app('website/builder/publish'), [WebsiteBuilderController::class, 'publish'], $websitePublishMw);
$router->post($app('website/builder/schedule'), [WebsiteBuilderController::class, 'schedule'], $websitePublishMw);
$router->post($app('website/builder/rollback'), [WebsiteBuilderController::class, 'rollback'], $websitePublishMw);
$router->post($app('website/builder/preview'), [WebsiteBuilderController::class, 'preview'], $websiteBuilderMw);

$router->get($app('website/theme'), [WebsiteThemeController::class, 'edit'], $websiteThemeMw);
$router->post($app('website/theme'), [WebsiteThemeController::class, 'save'], $websiteThemeMw);
$websiteThemeMarketMw = rateb_erp_mw('website', 'website.theme.marketplace', 'website-theme');
$websiteThemeImportMw = rateb_erp_mw('website', 'website.theme.import', 'website-theme');
$router->post($app('website/theme/marketplace/install'), [WebsiteThemeController::class, 'marketplaceInstall'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/activate'), [WebsiteThemeController::class, 'marketplaceActivate'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/preview'), [WebsiteThemeController::class, 'marketplacePreview'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/clear-preview'), [WebsiteThemeController::class, 'marketplaceClearPreview'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/duplicate'), [WebsiteThemeController::class, 'marketplaceDuplicate'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/reset'), [WebsiteThemeController::class, 'marketplaceReset'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/delete'), [WebsiteThemeController::class, 'marketplaceDelete'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/export'), [WebsiteThemeController::class, 'marketplaceExport'], $websiteThemeImportMw);
$router->post($app('website/theme/marketplace/import'), [WebsiteThemeController::class, 'marketplaceImport'], $websiteThemeImportMw);
$router->post($app('website/theme/marketplace/demo'), [WebsiteThemeController::class, 'marketplaceDemo'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/backup'), [WebsiteThemeController::class, 'marketplaceBackup'], $websiteThemeMarketMw);
$router->post($app('website/theme/marketplace/restore'), [WebsiteThemeController::class, 'marketplaceRestore'], $websiteThemeMarketMw);

$router->get($app('website/media'), [WebsiteMediaController::class, 'index'], $websiteMediaMw);
$router->post($app('website/media/upload'), [WebsiteMediaController::class, 'upload'], $websiteMediaMw);
$router->post($app('website/media/folder'), [WebsiteMediaController::class, 'createFolder'], $websiteMediaMw);

$router->get($app('website/menus'), [WebsiteMenusController::class, 'index'], $websiteBuilderMw);
$router->post($app('website/menus/items'), [WebsiteMenusController::class, 'saveItems'], $websiteBuilderMw);
$router->post($app('website/menus/footer'), [WebsiteMenusController::class, 'saveFooter'], $websiteBuilderMw);

$router->get($app('website/forms'), [WebsiteFormsController::class, 'index'], $websiteFormsMw);
$router->get($app('website/forms/create'), [WebsiteFormsController::class, 'create'], $websiteFormsMw);
$router->post($app('website/forms'), [WebsiteFormsController::class, 'store'], $websiteFormsMw);
$router->get($app('website/forms/{id}/edit'), [WebsiteFormsController::class, 'edit'], $websiteFormsMw);
$router->post($app('website/forms/{id}'), [WebsiteFormsController::class, 'update'], $websiteFormsMw);

require RATEB_ROOT . '/routes/company-access.php';
