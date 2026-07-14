<?php
declare(strict_types=1);

/**
 * Auth / guest / shared bootstrap routes (Phase AA.3).
 * Moved from routes/web.php — definitions unchanged.
 */

use Rateb\App\Controllers\Admin\AuthController as AdminAuthController;
use Rateb\App\Controllers\Admin\LocaleController;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;

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

$router->get('/locale/{locale}', [LocaleController::class, 'switch']);

$router->get('/documents/download/{id}', [\Rateb\App\Controllers\Shared\DocumentDownloadController::class, 'download'], [ErpAuthMiddleware::class]);
$router->get('/documents/view/{id}', [\Rateb\App\Controllers\Shared\DocumentDownloadController::class, 'viewFile'], [ErpAuthMiddleware::class]);
$router->get('/barcode/qr', [\Rateb\App\Controllers\Shared\BarcodeQrController::class, 'image'], [ErpAuthMiddleware::class]);
