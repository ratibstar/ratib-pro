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
use Rateb\App\Controllers\Admin\SuppliersController as AdminSuppliersController;
use Rateb\App\Controllers\Admin\SupportTicketsController;
use Rateb\App\Controllers\Admin\UsersController;
use Rateb\App\Core\Middleware\AdminAuthMiddleware;
use Rateb\App\Core\Middleware\GuestMiddleware;

/** @var Rateb\App\Core\Router $router */

$router->get('/', static function (): void {
    \Rateb\App\Core\Response::redirect(rateb_url('admin/login'));
});

$guest = [GuestMiddleware::class];
$admin = [AdminAuthMiddleware::class];

$router->get('/admin/login', [AdminAuthController::class, 'showLogin'], $guest);
$router->post('/admin/login', [AdminAuthController::class, 'login'], $guest);
$router->get('/admin/logout', [AdminAuthController::class, 'logout'], $admin);

$router->get('/admin', [AdminDashboardController::class, 'index'], $admin);
$router->get('/locale/{locale}', [LocaleController::class, 'switch']);

$router->get('/admin/companies', [CompaniesController::class, 'index'], $admin);
$router->get('/admin/companies/create', [CompaniesController::class, 'create'], $admin);
$router->post('/admin/companies', [CompaniesController::class, 'store'], $admin);
$router->get('/admin/companies/{id}/edit', [CompaniesController::class, 'edit'], $admin);
$router->post('/admin/companies/{id}', [CompaniesController::class, 'update'], $admin);
$router->post('/admin/companies/{id}/delete', [CompaniesController::class, 'destroy'], $admin);
$router->post('/admin/companies/{id}/suspend', [CompaniesController::class, 'suspend'], $admin);
$router->post('/admin/companies/{id}/activate', [CompaniesController::class, 'activate'], $admin);

foreach ([
    'subscriptions' => SubscriptionsController::class,
    'plans' => PlansController::class,
    'users' => UsersController::class,
    'roles' => RolesController::class,
    'permissions' => PermissionsController::class,
    'payments' => PaymentsController::class,
    'invoices' => InvoicesController::class,
    'email-templates' => EmailTemplatesController::class,
    'sms-templates' => SmsTemplatesController::class,
    'support-tickets' => SupportTicketsController::class,
    'suppliers' => AdminSuppliersController::class,
    'assets' => AdminAssetsController::class,
    'contracts' => AdminContractsController::class,
] as $path => $class) {
    $router->get('/admin/' . $path, [$class, 'index'], $admin);
    $router->get('/admin/' . $path . '/create', [$class, 'create'], $admin);
    $router->post('/admin/' . $path, [$class, 'store'], $admin);
    $router->get('/admin/' . $path . '/{id}/edit', [$class, 'edit'], $admin);
    $router->post('/admin/' . $path . '/{id}', [$class, 'update'], $admin);
    $router->post('/admin/' . $path . '/{id}/delete', [$class, 'destroy'], $admin);
}

$router->get('/admin/audit-logs', [AuditLogsController::class, 'index'], $admin);
$router->get('/admin/settings', [SettingsController::class, 'index'], $admin);
$router->post('/admin/settings', [SettingsController::class, 'save'], $admin);
$router->get('/admin/notifications', [AdminNotificationsController::class, 'index'], $admin);
$router->get('/admin/reports', [AdminReportsController::class, 'index'], $admin);
$router->get('/admin/procurement', [ProcurementController::class, 'index'], $admin);
$router->get('/admin/inventory', [AdminInventoryController::class, 'index'], $admin);
