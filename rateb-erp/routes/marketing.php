<?php
declare(strict_types=1);

use Rateb\App\Controllers\Marketing\CustomerPortalController;
use Rateb\App\Controllers\Marketing\MarketingAuthController;
use Rateb\App\Controllers\Marketing\MarketingController;
use Rateb\App\Controllers\Marketing\MarketingFormsController;
use Rateb\App\Controllers\Marketing\MarketingMediaController;

/** @var Rateb\App\Core\Router $router */

$router->get('/site/login', [MarketingAuthController::class, 'showLogin'], rateb_guest_mw());
$router->post('/site/login', [MarketingAuthController::class, 'login'], rateb_guest_mw());
$router->post('/site/login/2fa', [MarketingAuthController::class, 'verifyTwoFactor'], rateb_guest_mw());
$router->get('/site/register', [MarketingAuthController::class, 'showRegister'], rateb_guest_mw());
$router->post('/site/register', [MarketingAuthController::class, 'register'], rateb_guest_mw());

$router->get('/site/portal', [CustomerPortalController::class, 'index'], rateb_portal_mw());
$router->get('/site/portal/logout', [CustomerPortalController::class, 'logout']);

$router->get('/site', [MarketingController::class, 'home']);
$router->get('/site/sitemap.xml', [MarketingController::class, 'sitemap']);
$router->get('/site/robots.txt', [MarketingController::class, 'robots']);
$router->get('/site/blog/{slug}', [MarketingController::class, 'blogArticle']);
$router->get('/site/{slug}', [MarketingController::class, 'page']);

$router->post('/site/contact', [MarketingFormsController::class, 'contact']);
$router->post('/site/demo', [MarketingFormsController::class, 'demo']);
$router->post('/site/quote', [MarketingFormsController::class, 'quote']);
$router->post('/site/newsletter', [MarketingFormsController::class, 'newsletter']);

$router->get('/site/media/{file}', [MarketingMediaController::class, 'serve']);
