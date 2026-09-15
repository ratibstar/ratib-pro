<?php
declare(strict_types=1);

use Rateb\App\Controllers\Admin\MarketingNewsletterController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

// Marketing > Bulk Newsletter — reuses the CMS newsletter engine and the shared
// admin/cms/newsletter views, gated by marketing.newsletter.* permissions.
$router->get('/admin/marketing/newsletter', [MarketingNewsletterController::class, 'index'], rateb_platform_oversight_mw('marketing.newsletter.view'));
$router->get('/admin/marketing/newsletter/export', [MarketingNewsletterController::class, 'export'], rateb_platform_oversight_mw('marketing.newsletter.view'));
$router->post('/admin/marketing/newsletter/import', [MarketingNewsletterController::class, 'import'], rateb_platform_oversight_mw('marketing.newsletter.import'));
$router->get('/admin/marketing/newsletter/campaign', [MarketingNewsletterController::class, 'campaignForm'], rateb_platform_oversight_mw('marketing.newsletter.view'));
$router->post('/admin/marketing/newsletter/campaign/save', [MarketingNewsletterController::class, 'campaignSave'], rateb_platform_oversight_mw('marketing.newsletter.manage'));
$router->post('/admin/marketing/newsletter/campaign/send', [MarketingNewsletterController::class, 'campaignSend'], rateb_platform_oversight_mw('marketing.newsletter.send'));
$router->post('/admin/marketing/newsletter/campaign/pause', [MarketingNewsletterController::class, 'campaignPause'], rateb_platform_oversight_mw('marketing.newsletter.send'));
$router->post('/admin/marketing/newsletter/campaign/resume', [MarketingNewsletterController::class, 'campaignResume'], rateb_platform_oversight_mw('marketing.newsletter.send'));