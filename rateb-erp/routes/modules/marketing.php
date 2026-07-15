<?php
declare(strict_types=1);

use Rateb\App\Controllers\Marketing\CareerCandidateController;
use Rateb\App\Controllers\Marketing\CareerPortalController;
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
$router->get('/site/portal/profile', [CustomerPortalController::class, 'profile'], rateb_portal_mw());
$router->post('/site/portal/profile', [CustomerPortalController::class, 'updateProfile'], rateb_portal_mw());
$router->get('/site/portal/notifications', [CustomerPortalController::class, 'notifications'], rateb_portal_mw());
$router->get('/site/portal/logout', [CustomerPortalController::class, 'logout']);

// Phase WEBSITE-06 — Career portal (register before generic page slug route)
$router->get('/site/careers', [CareerPortalController::class, 'index']);
$router->get('/site/careers/search', [CareerPortalController::class, 'search']);
$router->get('/site/careers/category/{slug}', [CareerPortalController::class, 'category']);
$router->get('/site/careers/job/{slug}', [CareerPortalController::class, 'job']);
$router->get('/site/careers/job/{slug}/apply', [CareerPortalController::class, 'applyForm']);
$router->post('/site/careers/job/{slug}/apply', [CareerPortalController::class, 'apply']);

$router->get('/site/candidate/register', [CareerCandidateController::class, 'showRegister']);
$router->post('/site/candidate/register', [CareerCandidateController::class, 'register']);
$router->get('/site/candidate/login', [CareerCandidateController::class, 'showLogin']);
$router->post('/site/candidate/login', [CareerCandidateController::class, 'login']);
$router->get('/site/candidate/logout', [CareerCandidateController::class, 'logout']);
$router->get('/site/candidate', [CareerCandidateController::class, 'dashboard'], rateb_career_portal_mw());
$router->get('/site/candidate/applications', [CareerCandidateController::class, 'applications'], rateb_career_portal_mw());
$router->post('/site/candidate/withdraw/{id}', [CareerCandidateController::class, 'withdraw'], rateb_career_portal_mw());
$router->get('/site/candidate/saved', [CareerCandidateController::class, 'savedJobs'], rateb_career_portal_mw());
$router->post('/site/candidate/save/{careerId}', [CareerCandidateController::class, 'saveJob'], rateb_career_portal_mw());
$router->post('/site/candidate/unsave/{careerId}', [CareerCandidateController::class, 'unsaveJob'], rateb_career_portal_mw());
$router->get('/site/candidate/profile', [CareerCandidateController::class, 'profile'], rateb_career_portal_mw());
$router->post('/site/candidate/profile', [CareerCandidateController::class, 'updateProfile'], rateb_career_portal_mw());

$router->get('/site', [MarketingController::class, 'home']);
$router->get('/site/sitemap.xml', [MarketingController::class, 'sitemap']);
$router->get('/site/robots.txt', [MarketingController::class, 'robots']);
$router->get('/site/theme.css', [MarketingController::class, 'themeCss']);
$router->get('/site/preview/{token}', [MarketingController::class, 'preview']);
$router->get('/site/blog/{slug}', [MarketingController::class, 'blogArticle']);
$router->get('/site/{slug}', [MarketingController::class, 'page']);

$router->post('/site/contact', [MarketingFormsController::class, 'contact']);
$router->post('/site/demo', [MarketingFormsController::class, 'demo']);
$router->post('/site/quote', [MarketingFormsController::class, 'quote']);
$router->post('/site/newsletter', [MarketingFormsController::class, 'newsletter']);
$router->post('/site/forms/{slug}', [MarketingFormsController::class, 'websiteForm']);

$router->get('/site/media/{file}', [MarketingMediaController::class, 'serve']);
