<?php
declare(strict_types=1);

use Rateb\App\Controllers\Admin\AccessControlController;
use Rateb\App\Controllers\Admin\AuditLogsController;
use Rateb\App\Controllers\Admin\EmailTemplatesController;
use Rateb\App\Controllers\Admin\PermissionsController;
use Rateb\App\Controllers\Admin\PlansController;
use Rateb\App\Controllers\Admin\RolesController;
use Rateb\App\Controllers\Admin\SmsTemplatesController;
use Rateb\App\Controllers\Admin\SupportTicketsController;
use Rateb\App\Controllers\Admin\UsersController;
use Rateb\App\Controllers\Company\CompanyPlanController;

/** @var Rateb\App\Core\Router $router */

if (!function_exists('rateb_company_access_routes_enabled') || !rateb_company_access_routes_enabled()) {
    return;
}

$app = static fn (string $sub): string => '/' . rateb_app_route($sub);
$accessMw = static fn (string $perm = 'access.manage'): array => rateb_erp_mw('', $perm);
$settingsMw = static fn (string $perm = 'settings.manage'): array => rateb_erp_mw('', $perm);

$router->get($app('access-control'), [AccessControlController::class, 'index'], $accessMw());
$router->get($app('access-control/matrix'), [AccessControlController::class, 'matrix'], $accessMw());
$router->post($app('access-control/matrix'), [AccessControlController::class, 'saveMatrix'], $accessMw());

$accessCrud = [
    'users' => [UsersController::class, 'access.manage'],
    'roles' => [RolesController::class, 'access.manage'],
    'permissions' => [PermissionsController::class, 'access.manage'],
    'email-templates' => [EmailTemplatesController::class, 'settings.manage'],
    'sms-templates' => [SmsTemplatesController::class, 'settings.manage'],
    'support-tickets' => [SupportTicketsController::class, 'settings.manage'],
];

foreach ($accessCrud as $path => [$class, $perm]) {
    $mw = $perm === 'access.manage' ? $accessMw() : $settingsMw();
    $router->get($app($path), [$class, 'index'], $mw);
    $router->get($app($path . '/create'), [$class, 'create'], $mw);
    $router->post($app($path), [$class, 'store'], $mw);
    $router->post($app($path . '/bulk-delete'), [$class, 'bulkDestroy'], $mw);
    $router->get($app($path . '/export'), [$class, 'export'], $mw);
    $router->get($app($path . '/{id}/edit'), [$class, 'edit'], $mw);
    $router->post($app($path . '/{id}'), [$class, 'update'], $mw);
    $router->post($app($path . '/{id}/delete'), [$class, 'destroy'], $mw);
    $router->get($app($path . '/{id}/documents/panel'), [$class, 'documentsPanel'], $mw);
    $router->get($app($path . '/{id}/documents'), [$class, 'documents'], $mw);
    $router->post($app($path . '/{id}/documents'), [$class, 'storeDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}'), [$class, 'updateDocument'], $mw);
    $router->post($app($path . '/{id}/documents/{docId}/delete'), [$class, 'destroyDocument'], $mw);
}

$router->post($app('users/{id}/regenerate-barcode'), [UsersController::class, 'regenerateBarcode'], $accessMw());

$router->get($app('audit-logs'), [AuditLogsController::class, 'index'], $settingsMw());
$router->get($app('plans'), [CompanyPlanController::class, 'index'], $settingsMw());
$router->get($app('plans/{id}'), [PlansController::class, 'edit'], $settingsMw());
