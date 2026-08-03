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
$router->get('/m/{slug}/qr.png', [GuestMenuPublicController::class, 'qrPng']);

// Admin — POS module permissions
$router->get($gmApp(''), [GuestMenuAdminController::class, 'index'], $gmMw());
$router->post($gmApp(''), [GuestMenuAdminController::class, 'save'], $gmMw());
$router->get($gmApp('qr.png'), [GuestMenuAdminController::class, 'qrPng'], $gmMw());
