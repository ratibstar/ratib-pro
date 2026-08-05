<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Controllers\GuestMenuAdminController;
use Rateb\App\GuestMenu\Controllers\GuestMenuPublicController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$gmApp = static fn (string $sub = ''): string => '/' . rateb_app_route($sub === '' ? 'guest-menu' : 'guest-menu/' . ltrim($sub, '/'));

$gmMw = static fn () => rateb_erp_mw('pos', '', 'guest-menu');

// Public — no auth (QR scan entry)
$router->get('/m/{slug}', [GuestMenuPublicController::class, 'menu']);
$router->get('/m/{slug}/api/catalog', [GuestMenuPublicController::class, 'catalogApi']);
$router->post('/m/{slug}/api/order', [GuestMenuPublicController::class, 'submitOrder']);
$router->get('/m/{slug}/qr.png', [GuestMenuPublicController::class, 'qrPng']);

// Admin — POS module permissions
$router->get($gmApp(''), [GuestMenuAdminController::class, 'index'], $gmMw());
$router->post($gmApp(''), [GuestMenuAdminController::class, 'save'], $gmMw());
$router->get($gmApp('orders'), [GuestMenuAdminController::class, 'orders'], $gmMw());
$router->post($gmApp('orders/{orderId}/status'), [GuestMenuAdminController::class, 'orderStatus'], $gmMw());
$router->post($gmApp('import-catalog'), [GuestMenuAdminController::class, 'importCatalog'], $gmMw());
$router->post($gmApp('repair-menu-names'), [GuestMenuAdminController::class, 'repairMenuNames'], $gmMw());
$router->post($gmApp('delete-imported-catalog'), [GuestMenuAdminController::class, 'deleteImportedCatalog'], $gmMw());
$router->post($gmApp('cleanup-outside-pack'), [GuestMenuAdminController::class, 'cleanupOutsidePack'], $gmMw());
$router->get($gmApp('export-catalog'), [GuestMenuAdminController::class, 'exportCatalog'], $gmMw());
$router->post($gmApp('seed-platform-catalog'), [GuestMenuAdminController::class, 'seedPlatformCatalog'], $gmMw());
$router->post($gmApp('seed-demo'), [GuestMenuAdminController::class, 'seedDemo'], $gmMw());
$router->get($gmApp('qr.png'), [GuestMenuAdminController::class, 'qrPng'], $gmMw());
