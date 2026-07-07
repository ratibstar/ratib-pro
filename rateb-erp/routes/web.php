<?php
declare(strict_types=1);

use Rateb\App\Controllers\Admin\AuthController as AdminAuthController;
use Rateb\App\Controllers\Admin\AuditLogsController;
use Rateb\App\Controllers\Admin\CompaniesController;
use Rateb\App\Controllers\Admin\DashboardController as AdminDashboardController;
use Rateb\App\Controllers\Admin\EmailTemplatesController;
use Rateb\App\Controllers\Admin\InventoryController as AdminInventoryController;
use Rateb\App\Controllers\Admin\InvoicesController;
use Rateb\App\Controllers\Admin\LocaleController;
use Rateb\App\Controllers\Admin\PaymentsController;
use Rateb\App\Controllers\Admin\PermissionsController;
use Rateb\App\Controllers\Admin\ModulePageMetricsController;
use Rateb\App\Controllers\Admin\PlansController;
use Rateb\App\Controllers\Admin\ProcurementController;
use Rateb\App\Controllers\Admin\RfqOversightController;
use Rateb\App\Controllers\Admin\ReportsController as AdminReportsController;
use Rateb\App\Controllers\Admin\RolesController;
use Rateb\App\Controllers\Admin\SettingsController;
use Rateb\App\Controllers\Admin\SmsTemplatesController;
use Rateb\App\Controllers\Admin\SubscriptionsController;
use Rateb\App\Controllers\Admin\SupportTicketsController;
use Rateb\App\Controllers\Admin\UsersController;
use Rateb\App\Controllers\Admin\AccessControlController;
use Rateb\App\Controllers\Admin\AgencyUpdatesController;
use Rateb\App\Controllers\Admin\AccountingControlController;
use Rateb\App\Controllers\Admin\AccountingDashboardController;
use Rateb\App\Controllers\Admin\ChartOfAccountsController;
use Rateb\App\Controllers\Admin\AdminApprovalsController;
use Rateb\App\Controllers\Admin\AdminWorkflowsController;
use Rateb\App\Controllers\Admin\SupplierEvaluationsController;
use Rateb\App\Controllers\Admin\JournalEntriesController as AdminJournalEntriesController;
use Rateb\App\Controllers\Admin\ExecutiveDashboardController;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;
use Rateb\App\Core\Middleware\RequirePermissionMiddleware;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$router->get('/rateb-erp', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin'), 302);
});

$router->get('/favicon.ico', static function (): void {
    require RATEB_ROOT . '/public/favicon.php';
});

$router->get('/', static function (): void {
    if (\Rateb\App\Core\Auth::check()) {
        \Rateb\App\Core\Response::redirect(rateb_url(\Rateb\App\Core\Auth::homePath()));
        return;
    }
    if (function_exists('rateb_erp_public_prefix') && rateb_erp_public_prefix() === '') {
        (new \Rateb\App\Controllers\Marketing\MarketingController())->home();
        return;
    }
    \Rateb\App\Core\Response::redirect(rateb_url('site'));
});

$router->get('/login', [\Rateb\App\Controllers\Shared\LoginController::class, 'showLogin'], rateb_guest_mw());
$router->get('/scan/doc/{code}', [\Rateb\App\Controllers\Shared\DocumentScanController::class, 'show'], [ErpAuthMiddleware::class]);
$router->get('/scan/qr', [\Rateb\App\Controllers\Shared\BarcodeQrController::class, 'image']);
$router->post('/login', [\Rateb\App\Controllers\Shared\LoginController::class, 'login'], rateb_guest_mw());
$router->post('/login/2fa', [\Rateb\App\Controllers\Shared\LoginController::class, 'verifyTwoFactor'], rateb_guest_mw());
$router->post('/login/barcode', [\Rateb\App\Controllers\Shared\BarcodeLoginController::class, 'loginBarcode'], rateb_guest_mw());
$router->get('/login/scan', [\Rateb\App\Controllers\Shared\BarcodeLoginController::class, 'showScan'], rateb_guest_mw());
$router->get('/login/badge', [\Rateb\App\Controllers\Shared\QrLoginController::class, 'showBadge'], rateb_guest_mw());
$router->post('/api/login-barcode-pair', [\Rateb\App\Controllers\Shared\BarcodeLoginController::class, 'pairApi'], rateb_guest_mw());
$router->post('/api/qr-login', [\Rateb\App\Controllers\Shared\QrLoginController::class, 'api'], rateb_guest_mw());
$router->get('/logout', [\Rateb\App\Controllers\Shared\LoginController::class, 'logout']);

$router->get('/password/forgot', [\Rateb\App\Controllers\Shared\PasswordResetController::class, 'showForgot'], rateb_guest_mw());
$router->post('/password/forgot', [\Rateb\App\Controllers\Shared\PasswordResetController::class, 'sendLink'], rateb_guest_mw());
$router->get('/password/reset/{token}', [\Rateb\App\Controllers\Shared\PasswordResetController::class, 'showReset'], rateb_guest_mw());
$router->post('/password/reset/{token}', [\Rateb\App\Controllers\Shared\PasswordResetController::class, 'reset'], rateb_guest_mw());

$router->get('/admin/login', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('login'), 301);
});
$router->post('/admin/login', [\Rateb\App\Controllers\Shared\LoginController::class, 'login'], rateb_guest_mw());
$router->get('/admin/logout', [AdminAuthController::class, 'logout'], [ErpAuthMiddleware::class]);
$router->get('/admin/api/module-metrics', [ModulePageMetricsController::class, 'index'], [ErpAuthMiddleware::class]);

$router->get('/locale/{locale}', [LocaleController::class, 'switch']);

$router->get('/documents/download/{id}', [\Rateb\App\Controllers\Shared\DocumentDownloadController::class, 'download'], [ErpAuthMiddleware::class]);
$router->get('/documents/view/{id}', [\Rateb\App\Controllers\Shared\DocumentDownloadController::class, 'viewFile'], [ErpAuthMiddleware::class]);
$router->get('/barcode/qr', [\Rateb\App\Controllers\Shared\BarcodeQrController::class, 'image'], [ErpAuthMiddleware::class]);

$router->get('/admin', [AdminDashboardController::class, 'index'], [ErpAuthMiddleware::class]);
$router->get('/admin/executive-dashboard', [ExecutiveDashboardController::class, 'index'], rateb_admin_mw('executive.dashboard.view'));

$router->get('/admin/companies/branches', [CompaniesController::class, 'branchesHub'], rateb_platform_oversight_mw('companies.view'));
$router->get('/admin/companies/{id}/branches', [CompaniesController::class, 'manageBranches'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/{id}/branches', [CompaniesController::class, 'manageBranches'], rateb_platform_oversight_mw('companies.manage'));
$router->get('/admin/companies', [CompaniesController::class, 'index'], rateb_platform_oversight_mw('companies.view'));
$router->get('/admin/companies/create', [CompaniesController::class, 'create'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies', [CompaniesController::class, 'store'], rateb_platform_oversight_mw('companies.manage'));
$router->get('/admin/companies/{id}/edit', [CompaniesController::class, 'edit'], rateb_platform_oversight_mw('company_plans.manage'));
$router->post('/admin/companies/{id}', [CompaniesController::class, 'update'], rateb_platform_oversight_mw('company_plans.manage'));
$router->post('/admin/companies/{id}/delete', [CompaniesController::class, 'destroy'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/{id}/suspend', [CompaniesController::class, 'suspend'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/{id}/activate', [CompaniesController::class, 'activate'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/bulk-delete', [CompaniesController::class, 'bulkDestroy'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/bulk-suspend', [CompaniesController::class, 'bulkSuspend'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/bulk-activate', [CompaniesController::class, 'bulkActivate'], rateb_platform_oversight_mw('companies.manage'));
$router->get('/admin/companies/{id}/documents/panel', [CompaniesController::class, 'documentsPanel'], rateb_platform_oversight_mw('companies.view'));
$router->get('/admin/companies/{id}/documents', [CompaniesController::class, 'documents'], rateb_platform_oversight_mw('companies.view'));
$router->post('/admin/companies/{id}/documents', [CompaniesController::class, 'storeDocument'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/{id}/documents/{docId}', [CompaniesController::class, 'updateDocument'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/companies/{id}/documents/{docId}/delete', [CompaniesController::class, 'destroyDocument'], rateb_platform_oversight_mw('companies.manage'));

$router->get('/admin/agency-updates', [AgencyUpdatesController::class, 'index'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/agency-updates/push', [AgencyUpdatesController::class, 'push'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/agency-updates/link', [AgencyUpdatesController::class, 'link'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/agency-updates/sync-files', [AgencyUpdatesController::class, 'syncFiles'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/agency-updates/restore-admin', [AgencyUpdatesController::class, 'restoreAdmin'], rateb_platform_oversight_mw('companies.manage'));
$router->post('/admin/agency-updates/reset-data', [AgencyUpdatesController::class, 'resetData'], rateb_platform_oversight_mw('companies.manage'));

$router->get('/admin/access-control', [AccessControlController::class, 'index'], rateb_admin_mw('access.manage'));
$router->get('/admin/access-control/matrix', [AccessControlController::class, 'matrix'], rateb_admin_mw('access.manage'));
$router->post('/admin/access-control/matrix', [AccessControlController::class, 'saveMatrix'], rateb_admin_mw('access.manage'));

$router->get('/admin/accounting', [AccountingDashboardController::class, 'index'], rateb_platform_oversight_mw('accounting.view'));
$router->post('/admin/accounting/sync', [AccountingDashboardController::class, 'sync'], rateb_platform_oversight_mw('accounting.post'));

$router->get('/admin/chart-of-accounts', [ChartOfAccountsController::class, 'index'], rateb_platform_oversight_mw('accounting.view'));
$router->get('/admin/coa-tree', [ChartOfAccountsController::class, 'coaTree'], rateb_platform_oversight_mw('accounting.view'));
$router->get('/admin/chart-of-accounts/create', [ChartOfAccountsController::class, 'create'], rateb_platform_oversight_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts', [ChartOfAccountsController::class, 'store'], rateb_platform_oversight_mw('accounting.manage'));
$router->get('/admin/chart-of-accounts/{id}/edit', [ChartOfAccountsController::class, 'edit'], rateb_platform_oversight_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'update'], rateb_platform_oversight_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts/{id}/delete', [ChartOfAccountsController::class, 'destroy'], rateb_platform_oversight_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts/bulk-delete', [ChartOfAccountsController::class, 'bulkDestroy'], rateb_platform_oversight_mw('accounting.manage'));

$router->get('/admin/journal-entries', [AdminJournalEntriesController::class, 'index'], rateb_platform_oversight_mw('accounting.view'));
$router->get('/admin/journal-entries/{id}', [AdminJournalEntriesController::class, 'show'], rateb_platform_oversight_mw('accounting.view'));

$crudRoutes = [
    'users' => [UsersController::class, 'access.manage'],
    'roles' => [RolesController::class, 'access.manage'],
    'permissions' => [PermissionsController::class, 'access.manage'],
    'email-templates' => [EmailTemplatesController::class, 'settings.manage'],
    'sms-templates' => [SmsTemplatesController::class, 'settings.manage'],
    'support-tickets' => [SupportTicketsController::class, 'settings.manage'],
];

$platformCrudRoutes = [
    'subscriptions' => [SubscriptionsController::class, 'subscriptions.manage'],
    'plans' => [PlansController::class, 'plans.manage'],
];

foreach ($platformCrudRoutes as $path => [$class, $perm]) {
    $router->get('/admin/' . $path, [$class, 'index'], rateb_platform_oversight_mw($perm));
    $router->get('/admin/' . $path . '/create', [$class, 'create'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path, [$class, 'store'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/bulk-delete', [$class, 'bulkDestroy'], rateb_platform_oversight_mw($perm));
    $router->get('/admin/' . $path . '/export', [$class, 'export'], rateb_platform_oversight_mw($perm));
    $router->get('/admin/' . $path . '/{id}/edit', [$class, 'edit'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/{id}', [$class, 'update'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/{id}/delete', [$class, 'destroy'], rateb_platform_oversight_mw($perm));
    $router->get('/admin/' . $path . '/{id}/documents/panel', [$class, 'documentsPanel'], rateb_platform_oversight_mw($perm));
    $router->get('/admin/' . $path . '/{id}/documents', [$class, 'documents'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents', [$class, 'storeDocument'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}', [$class, 'updateDocument'], rateb_platform_oversight_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}/delete', [$class, 'destroyDocument'], rateb_platform_oversight_mw($perm));
}

foreach ($crudRoutes as $path => [$class, $perm]) {
    $router->get('/admin/' . $path, [$class, 'index'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/create', [$class, 'create'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path, [$class, 'store'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/bulk-delete', [$class, 'bulkDestroy'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/export', [$class, 'export'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/{id}/edit', [$class, 'edit'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}', [$class, 'update'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}/delete', [$class, 'destroy'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/{id}/documents/panel', [$class, 'documentsPanel'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/{id}/documents', [$class, 'documents'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents', [$class, 'storeDocument'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}', [$class, 'updateDocument'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}/delete', [$class, 'destroyDocument'], rateb_admin_mw($perm));
}

$router->post('/admin/users/{id}/regenerate-barcode', [UsersController::class, 'regenerateBarcode'], rateb_admin_mw('access.manage'));

$billingCrud = [
    'payments' => PaymentsController::class,
    'invoices' => InvoicesController::class,
];
foreach ($billingCrud as $path => $class) {
    $router->get('/admin/' . $path, [$class, 'index'], rateb_platform_oversight_mw('accounting.view'));
    $router->get('/admin/' . $path . '/create', [$class, 'create'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path, [$class, 'store'], rateb_platform_oversight_mw('billing.manage'));
    if ($path === 'invoices') {
        $router->get('/admin/invoices/subscription-lookup', [$class, 'subscriptionLookup'], rateb_platform_oversight_mw('billing.manage'));
        $router->get('/admin/invoices/tax-profile-lookup', [$class, 'taxProfileLookup'], rateb_platform_oversight_mw('billing.manage'));
        $router->get('/admin/invoices/chart-accounts-lookup', [$class, 'chartAccountsLookup'], rateb_platform_oversight_mw('billing.manage'));
        $router->get('/admin/invoices/{id}/preview', [$class, 'preview'], rateb_platform_oversight_mw('accounting.view'));
        $router->post('/admin/invoices/preview-draft', [$class, 'previewDraft'], rateb_platform_oversight_mw('billing.manage'));
    }
    $router->post('/admin/' . $path . '/bulk-delete', [$class, 'bulkDestroy'], rateb_platform_oversight_mw('billing.manage'));
    $router->get('/admin/' . $path . '/export', [$class, 'export'], rateb_platform_oversight_mw('billing.manage'));
    $router->get('/admin/' . $path . '/{id}/edit', [$class, 'edit'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path . '/{id}', [$class, 'update'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path . '/{id}/delete', [$class, 'destroy'], rateb_platform_oversight_mw('billing.manage'));
    $router->get('/admin/' . $path . '/{id}/documents/panel', [$class, 'documentsPanel'], rateb_platform_oversight_mw('billing.manage'));
    $router->get('/admin/' . $path . '/{id}/documents', [$class, 'documents'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path . '/{id}/documents', [$class, 'storeDocument'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}', [$class, 'updateDocument'], rateb_platform_oversight_mw('billing.manage'));
    $router->post('/admin/' . $path . '/{id}/documents/{docId}/delete', [$class, 'destroyDocument'], rateb_platform_oversight_mw('billing.manage'));
}

$router->get('/admin/audit-logs', [AuditLogsController::class, 'index'], rateb_admin_mw('settings.manage'));

$router->get('/admin/accounting-control', [AccountingControlController::class, 'dashboard'], rateb_admin_mw('accounting.dashboard'));
$router->get('/admin/accounting-control/events', [AccountingControlController::class, 'events'], rateb_admin_mw('accounting.events'));
$router->get('/admin/accounting-control/replay', [AccountingControlController::class, 'replay'], rateb_admin_mw('accounting.replay'));
$router->get('/admin/accounting-control/audit', [AccountingControlController::class, 'audit'], rateb_admin_mw('accounting.audit'));
$router->get('/admin/accounting-control/projections', [AccountingControlController::class, 'projections'], rateb_admin_mw('accounting.projections'));
$router->get('/admin/accounting-control/consolidation', [AccountingControlController::class, 'consolidation'], rateb_admin_mw('accounting.consolidation'));
$router->get('/admin/accounting-control/drift', [AccountingControlController::class, 'drift'], rateb_admin_mw('accounting.drift'));
$router->get('/admin/accounting-control/reconciliation', [AccountingControlController::class, 'reconciliation'], rateb_admin_mw('accounting.reconciliation'));
$router->get('/admin/accounting-control/integrity', [AccountingControlController::class, 'integrity'], rateb_admin_mw('accounting.integrity'));
$router->get('/admin/accounting-control/settings', [AccountingControlController::class, 'settings'], rateb_admin_mw('accounting.dashboard'));
$router->get('/admin/accounting-control/health', [AccountingControlController::class, 'health'], rateb_admin_mw('accounting.system_health'));
$router->get('/admin/accounting-control/timeline', [AccountingControlController::class, 'timeline'], rateb_admin_mw('accounting.dashboard'));
$router->get('/admin/accounting-control/notifications', [AccountingControlController::class, 'notifications'], rateb_admin_mw('accounting.dashboard'));
$router->get('/admin/accounting-control/diagnostics', [AccountingControlController::class, 'diagnostics'], rateb_admin_mw('accounting.system_health'));
$router->get('/admin/accounting-control/api/{resource}', [AccountingControlController::class, 'api'], rateb_admin_mw());
$router->post('/admin/accounting-control/api/{resource}', [AccountingControlController::class, 'api'], rateb_admin_mw());
$router->get('/admin/login-activity', [\Rateb\App\Controllers\Admin\LoginActivityController::class, 'index'], rateb_admin_mw('settings.manage'));
$router->get('/admin/queue-monitor', [\Rateb\App\Controllers\Admin\QueueMonitorController::class, 'index'], rateb_platform_oversight_mw('settings.manage'));
$router->post('/admin/queue-monitor/retry', [\Rateb\App\Controllers\Admin\QueueMonitorController::class, 'retry'], rateb_platform_oversight_mw('settings.manage'));
$router->get('/admin/automation-health', [\Rateb\App\Controllers\Admin\AutomationDashboardController::class, 'index'], rateb_platform_oversight_mw('settings.manage'));
$router->get('/admin/settings', [SettingsController::class, 'index'], rateb_admin_mw('settings.manage'));
$router->post('/admin/settings', [SettingsController::class, 'save'], rateb_admin_mw('settings.manage'));
$router->post('/admin/settings/save-mail', [SettingsController::class, 'saveMail'], rateb_admin_mw('settings.manage'));
$router->post('/admin/settings/test-mail', [SettingsController::class, 'testMail'], rateb_admin_mw('settings.manage'));
$router->get('/admin/tools/fix-arabic', [SettingsController::class, 'fixArabic'], rateb_admin_mw('settings.manage'));
$router->get('/admin/reports', [AdminReportsController::class, 'index'], rateb_platform_oversight_mw('reports.view'));
$router->get('/admin/procurement', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('purchase-requests')), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/inventory', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('inventory')), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/rfq', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('rfq')), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/stock-movements', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('stock-movements')), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/stock-movements/export', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('stock-movements') . '/export'), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/supplier-evaluations', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin/oversight/supplier-evaluations'), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/medical-devices', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('medical-devices')), 301);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/notifications', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route('notifications')), 301);
}, [ErpAuthMiddleware::class]);
$legacyOpsResources = ['suppliers', 'assets', 'contracts'];
foreach ($legacyOpsResources as $legacyOps) {
    $router->get('/admin/' . $legacyOps, static function () use ($legacyOps): void {
        \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route($legacyOps)), 301);
    }, [ErpAuthMiddleware::class]);
    $router->get('/admin/' . $legacyOps . '/{rest:.+}', static function (array $params) use ($legacyOps): void {
        \Rateb\App\Core\Response::redirect(rateb_url(rateb_app_route($legacyOps) . '/' . ($params['rest'] ?? '')), 301);
    }, [ErpAuthMiddleware::class]);
}
$router->get('/admin/workflows', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin/oversight/workflows'), 301);
}, [ErpAuthMiddleware::class]);
$router->post('/admin/workflows', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin/oversight/workflows'), 307);
}, [ErpAuthMiddleware::class]);
$router->get('/admin/oversight/approvals', [AdminApprovalsController::class, 'index'], rateb_platform_oversight_mw('workflows.view'));
$router->get('/admin/oversight/companies-approvals', [AdminApprovalsController::class, 'companiesApprovals'], rateb_platform_oversight_mw('companies.view'));
$router->get('/admin/oversight/approvals/count', [AdminApprovalsController::class, 'count'], rateb_platform_oversight_mw('workflows.view'));
$router->get('/admin/oversight/approvals/detail', [AdminApprovalsController::class, 'detail'], rateb_platform_oversight_mw('workflows.view'));
$router->post('/admin/oversight/approvals/decide', [AdminApprovalsController::class, 'decideAction'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/approvals/action', [AdminApprovalsController::class, 'decideAction'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/approvals/approve', [AdminApprovalsController::class, 'approve'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/approvals/reject', [AdminApprovalsController::class, 'reject'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/approvals/undo', [AdminApprovalsController::class, 'undo'], rateb_platform_oversight_mw('workflows.manage'));
$router->get('/admin/oversight/supplier-evaluations', [SupplierEvaluationsController::class, 'index'], rateb_platform_oversight_mw('procurement.manage'));
$router->get('/admin/oversight/procurement', [ProcurementController::class, 'index'], rateb_platform_oversight_mw('procurement.manage'));
$router->get('/admin/oversight/rfq', [RfqOversightController::class, 'index'], rateb_platform_oversight_mw('procurement.manage'));
$router->get('/admin/oversight/inventory', [AdminInventoryController::class, 'index'], rateb_platform_oversight_mw('inventory.manage'));
$router->get('/admin/oversight/workflows', [AdminWorkflowsController::class, 'index'], rateb_platform_oversight_mw('workflows.view'));
$router->post('/admin/oversight/workflows', [AdminWorkflowsController::class, 'store'], rateb_platform_oversight_mw('workflows.manage'));
$router->get('/admin/oversight/workflows/{id}/edit', [AdminWorkflowsController::class, 'edit'], rateb_platform_oversight_mw('workflows.view'));
$router->post('/admin/oversight/workflows/{id}', [AdminWorkflowsController::class, 'update'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/workflows/{id}/delete', [AdminWorkflowsController::class, 'destroy'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/workflows/{id}/toggle', [AdminWorkflowsController::class, 'toggle'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/workflows/{id}/steps', [AdminWorkflowsController::class, 'storeStep'], rateb_platform_oversight_mw('workflows.manage'));
$router->post('/admin/oversight/workflows/{id}/steps/{stepId}/delete', [AdminWorkflowsController::class, 'destroyStep'], rateb_platform_oversight_mw('workflows.manage'));
