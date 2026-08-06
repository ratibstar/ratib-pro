<?php
declare(strict_types=1);

use Rateb\App\Controllers\Api\ApiController;
use Rateb\App\Core\Middleware\ApiAuthMiddleware;
use Rateb\App\Core\Middleware\ApiModuleMiddleware;

/** @var Rateb\App\Core\Router $router */

$api = [ApiAuthMiddleware::class];

if (!function_exists('rateb_api_mw')) {
    function rateb_api_mw(string $module = ''): array
    {
        $stack = [ApiAuthMiddleware::class];
        if ($module !== '') {
            $stack[] = [ApiModuleMiddleware::class, $module];
        }
        return $stack;
    }
}

$router->get('/api/v1', [ApiController::class, 'index']);
$router->post('/api/v1/auth/token', [ApiController::class, 'createToken']);

$router->get('/api/v1/hr/me', [\Rateb\App\Controllers\Api\HrEssMeController::class, 'me'], $api);
$router->get('/api/v1/hr/profile', [\Rateb\App\Controllers\Api\HrEssProfileController::class, 'show'], $api);
$router->get('/api/v1/hr/attendance/today', [\Rateb\App\Controllers\Api\HrEssAttendanceController::class, 'today'], $api);
$router->get('/api/v1/hr/attendance/history', [\Rateb\App\Controllers\Api\HrEssAttendanceController::class, 'history'], $api);
$router->post('/api/v1/hr/attendance/check-in', [\Rateb\App\Controllers\Api\HrEssAttendanceController::class, 'checkIn'], $api);
$router->post('/api/v1/hr/attendance/check-out', [\Rateb\App\Controllers\Api\HrEssAttendanceController::class, 'checkOut'], $api);
$router->get('/api/v1/hr/leave/balances', [\Rateb\App\Controllers\Api\HrEssLeaveController::class, 'balances'], $api);
$router->get('/api/v1/hr/leave/requests', [\Rateb\App\Controllers\Api\HrEssLeaveController::class, 'requests'], $api);
$router->get('/api/v1/hr/leave/requests/{id}', [\Rateb\App\Controllers\Api\HrEssLeaveController::class, 'show'], $api);
$router->post('/api/v1/hr/leave/apply', [\Rateb\App\Controllers\Api\HrEssLeaveController::class, 'apply'], $api);
$router->get('/api/v1/hr/payslips', [\Rateb\App\Controllers\Api\HrEssPayslipController::class, 'index'], $api);
$router->get('/api/v1/hr/payslips/{id}', [\Rateb\App\Controllers\Api\HrEssPayslipController::class, 'show'], $api);
$router->get('/api/v1/hr/payslips/{id}/file', [\Rateb\App\Controllers\Api\HrEssPayslipController::class, 'file'], $api);
$router->get('/api/v1/hr/documents', [\Rateb\App\Controllers\Api\HrEssDocumentController::class, 'index'], $api);
$router->get('/api/v1/hr/documents/{id}', [\Rateb\App\Controllers\Api\HrEssDocumentController::class, 'show'], $api);
$router->get('/api/v1/hr/documents/{id}/file', [\Rateb\App\Controllers\Api\HrEssDocumentController::class, 'file'], $api);
$router->get('/api/v1/hr/notifications', [\Rateb\App\Controllers\Api\HrEssNotificationsController::class, 'list'], $api);
$router->post('/api/v1/hr/notifications/read-all', [\Rateb\App\Controllers\Api\HrEssNotificationsController::class, 'markAllRead'], $api);
$router->post('/api/v1/hr/notifications/{id}/read', [\Rateb\App\Controllers\Api\HrEssNotificationsController::class, 'markRead'], $api);

$router->get('/api/v1/hr/dashboard', [\Rateb\App\Controllers\Api\HrEssDashboardController::class, 'summary'], $api);
$router->get('/api/v1/hr/requests', [\Rateb\App\Controllers\Api\HrEssEmployeeRequestsController::class, 'list'], $api);
$router->get('/api/v1/hr/requests/{id}', [\Rateb\App\Controllers\Api\HrEssEmployeeRequestsController::class, 'show'], $api);
$router->post('/api/v1/hr/requests', [\Rateb\App\Controllers\Api\HrEssEmployeeRequestsController::class, 'create'], $api);
$router->get('/api/v1/hr/permission-requests', [\Rateb\App\Controllers\Api\HrEssPermissionRequestsController::class, 'list'], $api);
$router->get('/api/v1/hr/permission-requests/{id}', [\Rateb\App\Controllers\Api\HrEssPermissionRequestsController::class, 'show'], $api);
$router->post('/api/v1/hr/permission-requests', [\Rateb\App\Controllers\Api\HrEssPermissionRequestsController::class, 'submit'], $api);
$router->get('/api/v1/hr/ratings', [\Rateb\App\Controllers\Api\HrEssRatingsController::class, 'summary'], $api);
$router->get('/api/v1/hr/payment-methods', [\Rateb\App\Controllers\Api\HrEssPaymentMethodsController::class, 'list'], $api);
$router->post('/api/v1/hr/settings/change-password', [\Rateb\App\Controllers\Api\HrEssSettingsController::class, 'changePassword'], $api);

$router->get('/api/mobile/config', [\Rateb\App\Controllers\Api\MobileConfigController::class, 'config'], $api);

$router->post('/api/v1/mobile/devices/register', [\Rateb\App\Controllers\Api\MobileDeviceController::class, 'register'], $api);
$router->post('/api/v1/mobile/devices/heartbeat', [\Rateb\App\Controllers\Api\MobileDeviceController::class, 'heartbeat'], $api);
$router->post('/api/v1/mobile/devices/push-token', [\Rateb\App\Controllers\Api\MobileDeviceController::class, 'pushToken'], $api);
$router->post('/api/v1/mobile/devices/{id}/revoke', [\Rateb\App\Controllers\Api\MobileDeviceController::class, 'revoke'], $api);

$router->get('/api/v1/dashboard', [ApiController::class, 'dashboard'], $api);
$router->get('/api/v1/companies', [ApiController::class, 'listCompanies'], $api);
$router->post('/api/v1/companies', [ApiController::class, 'createCompany'], $api);
$router->get('/api/v1/companies/{id}', [ApiController::class, 'getCompany'], $api);
$router->get('/api/v1/suppliers', [ApiController::class, 'listSuppliers'], rateb_api_mw('suppliers'));
$router->post('/api/v1/suppliers', [ApiController::class, 'createSupplier'], rateb_api_mw('suppliers'));
$router->get('/api/v1/purchase-requests', [ApiController::class, 'listPurchaseRequests'], rateb_api_mw('procurement'));
$router->post('/api/v1/purchase-requests', [ApiController::class, 'createPurchaseRequest'], rateb_api_mw('procurement'));
$router->get('/api/v1/purchase-orders', [ApiController::class, 'listPurchaseOrders'], rateb_api_mw('procurement'));
$router->post('/api/v1/purchase-orders', [ApiController::class, 'createPurchaseOrder'], rateb_api_mw('procurement'));
$router->get('/api/v1/inventory', [ApiController::class, 'listInventory'], rateb_api_mw('inventory'));
$router->post('/api/v1/inventory', [ApiController::class, 'createInventory'], rateb_api_mw('inventory'));

if (is_file(RATEB_ROOT . '/modules/pos/routes/pos-api.php')) {
    require RATEB_ROOT . '/modules/pos/routes/pos-api.php';
}
if (is_file(RATEB_ROOT . '/modules/logistics/routes/logistics-api.php')) {
    require RATEB_ROOT . '/modules/logistics/routes/logistics-api.php';
}
if (is_file(RATEB_ROOT . '/modules/pos/routes/pos-api-v2.php')) {
    require RATEB_ROOT . '/modules/pos/routes/pos-api-v2.php';
}
if (is_file(RATEB_ROOT . '/offline/server/routes/offline-api.php')) {
    require RATEB_ROOT . '/offline/server/routes/offline-api.php';
}
if (is_file(RATEB_ROOT . '/offline/server/routes/offline-monitoring-api.php')) {
    require RATEB_ROOT . '/offline/server/routes/offline-monitoring-api.php';
}
