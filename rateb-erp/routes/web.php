<?php
declare(strict_types=1);

use Rateb\App\Controllers\Admin\AuthController as AdminAuthController;
use Rateb\App\Controllers\Admin\AssetsController as AdminAssetsController;
use Rateb\App\Controllers\Admin\AuditLogsController;
use Rateb\App\Controllers\Admin\CompaniesController;
use Rateb\App\Controllers\Admin\ContractsController as AdminContractsController;
use Rateb\App\Controllers\Admin\DashboardController as AdminDashboardController;
use Rateb\App\Controllers\Admin\EmailTemplatesController;
use Rateb\App\Controllers\Admin\InventoryController as AdminInventoryController;
use Rateb\App\Controllers\Admin\InvoicesController;
use Rateb\App\Controllers\Admin\LocaleController;
use Rateb\App\Controllers\Admin\NotificationsController as AdminNotificationsController;
use Rateb\App\Controllers\Admin\PaymentsController;
use Rateb\App\Controllers\Admin\PermissionsController;
use Rateb\App\Controllers\Admin\PlansController;
use Rateb\App\Controllers\Admin\ProcurementController;
use Rateb\App\Controllers\Admin\ReportsController as AdminReportsController;
use Rateb\App\Controllers\Admin\RolesController;
use Rateb\App\Controllers\Admin\SettingsController;
use Rateb\App\Controllers\Admin\SmsTemplatesController;
use Rateb\App\Controllers\Admin\SubscriptionsController;
use Rateb\App\Controllers\Admin\SupplierEvaluationsController as AdminSupplierEvaluationsController;
use Rateb\App\Controllers\Admin\SuppliersController as AdminSuppliersController;
use Rateb\App\Controllers\Admin\SupportTicketsController;
use Rateb\App\Controllers\Admin\UsersController;
use Rateb\App\Controllers\Admin\AccessControlController;
use Rateb\App\Controllers\Admin\AccountingDashboardController;
use Rateb\App\Controllers\Admin\ChartOfAccountsController;
use Rateb\App\Controllers\Admin\JournalEntriesController as AdminJournalEntriesController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$router->get('/', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin/login'));
});

$router->get('/admin/login', [AdminAuthController::class, 'showLogin'], rateb_guest_mw());
$router->post('/admin/login', [AdminAuthController::class, 'login'], rateb_guest_mw());
$router->get('/admin/logout', [AdminAuthController::class, 'logout'], rateb_admin_mw());

$router->get('/locale/{locale}', [LocaleController::class, 'switch']);

$router->get('/admin', [AdminDashboardController::class, 'index'], rateb_admin_mw('dashboard.view'));

$router->get('/admin/companies', [CompaniesController::class, 'index'], rateb_admin_mw('companies.view'));
$router->get('/admin/companies/create', [CompaniesController::class, 'create'], rateb_admin_mw('companies.manage'));
$router->post('/admin/companies', [CompaniesController::class, 'store'], rateb_admin_mw('companies.manage'));
$router->get('/admin/companies/{id}/edit', [CompaniesController::class, 'edit'], rateb_admin_mw('company_plans.manage'));
$router->post('/admin/companies/{id}', [CompaniesController::class, 'update'], rateb_admin_mw('company_plans.manage'));
$router->post('/admin/companies/{id}/delete', [CompaniesController::class, 'destroy'], rateb_admin_mw('companies.manage'));
$router->post('/admin/companies/{id}/suspend', [CompaniesController::class, 'suspend'], rateb_admin_mw('companies.manage'));
$router->post('/admin/companies/{id}/activate', [CompaniesController::class, 'activate'], rateb_admin_mw('companies.manage'));

$router->get('/admin/access-control', [AccessControlController::class, 'index'], rateb_admin_mw('access.manage'));

$router->get('/admin/accounting', [AccountingDashboardController::class, 'index'], rateb_admin_mw('accounting.view'));
$router->post('/admin/accounting/sync', [AccountingDashboardController::class, 'sync'], rateb_admin_mw('accounting.post'));

$router->get('/admin/chart-of-accounts', [ChartOfAccountsController::class, 'index'], rateb_admin_mw('accounting.view'));
$router->get('/admin/chart-of-accounts/create', [ChartOfAccountsController::class, 'create'], rateb_admin_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts', [ChartOfAccountsController::class, 'store'], rateb_admin_mw('accounting.manage'));
$router->get('/admin/chart-of-accounts/{id}/edit', [ChartOfAccountsController::class, 'edit'], rateb_admin_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'update'], rateb_admin_mw('accounting.manage'));
$router->post('/admin/chart-of-accounts/{id}/delete', [ChartOfAccountsController::class, 'destroy'], rateb_admin_mw('accounting.manage'));

$router->get('/admin/journal-entries', [AdminJournalEntriesController::class, 'index'], rateb_admin_mw('accounting.view'));
$router->get('/admin/journal-entries/{id}', [AdminJournalEntriesController::class, 'show'], rateb_admin_mw('accounting.view'));

$crudRoutes = [
    'subscriptions' => [SubscriptionsController::class, 'subscriptions.manage'],
    'plans' => [PlansController::class, 'plans.manage'],
    'users' => [UsersController::class, 'access.manage'],
    'roles' => [RolesController::class, 'access.manage'],
    'permissions' => [PermissionsController::class, 'access.manage'],
    'payments' => [PaymentsController::class, 'accounting.view'],
    'invoices' => [InvoicesController::class, 'accounting.view'],
    'email-templates' => [EmailTemplatesController::class, 'settings.manage'],
    'sms-templates' => [SmsTemplatesController::class, 'settings.manage'],
    'support-tickets' => [SupportTicketsController::class, 'settings.manage'],
    'suppliers' => [AdminSuppliersController::class, 'suppliers.manage'],
    'assets' => [AdminAssetsController::class, 'assets.manage'],
    'contracts' => [AdminContractsController::class, 'contracts.manage'],
];

foreach ($crudRoutes as $path => [$class, $perm]) {
    $router->get('/admin/' . $path, [$class, 'index'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/create', [$class, 'create'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path, [$class, 'store'], rateb_admin_mw($perm));
    $router->get('/admin/' . $path . '/{id}/edit', [$class, 'edit'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}', [$class, 'update'], rateb_admin_mw($perm));
    $router->post('/admin/' . $path . '/{id}/delete', [$class, 'destroy'], rateb_admin_mw($perm));
}

$router->get('/admin/audit-logs', [AuditLogsController::class, 'index'], rateb_admin_mw('settings.manage'));
$router->get('/admin/settings', [SettingsController::class, 'index'], rateb_admin_mw('settings.manage'));
$router->post('/admin/settings', [SettingsController::class, 'save'], rateb_admin_mw('settings.manage'));
$router->get('/admin/notifications', [AdminNotificationsController::class, 'index'], rateb_admin_mw('dashboard.view'));
$router->get('/admin/reports', [AdminReportsController::class, 'index'], rateb_admin_mw('reports.view'));
$router->get('/admin/procurement', [ProcurementController::class, 'index'], rateb_admin_mw('procurement.manage'));
$router->get('/admin/inventory', [AdminInventoryController::class, 'index'], rateb_admin_mw('inventory.manage'));
$router->get('/admin/supplier-evaluations', [AdminSupplierEvaluationsController::class, 'index'], rateb_admin_mw('evaluations.view'));
