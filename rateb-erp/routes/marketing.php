<?php
declare(strict_types=1);

use Rateb\App\Controllers\Marketing\MarketingController;
use Rateb\App\Controllers\Marketing\MarketingFormsController;
use Rateb\App\Controllers\Marketing\MarketingMediaController;

/** @var Rateb\App\Core\Router $router */

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
