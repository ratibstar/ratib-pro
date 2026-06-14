<?php
declare(strict_types=1);

use Rateb\App\Controllers\Admin\CmsAboutController;
use Rateb\App\Controllers\Admin\CmsAnalyticsController;
use Rateb\App\Controllers\Admin\CmsBlocksController;
use Rateb\App\Controllers\Admin\CmsBlogArticlesController;
use Rateb\App\Controllers\Admin\CmsBlogAuthorsController;
use Rateb\App\Controllers\Admin\CmsBlogCategoriesController;
use Rateb\App\Controllers\Admin\CmsBlogTagsController;
use Rateb\App\Controllers\Admin\CmsCareersController;
use Rateb\App\Controllers\Admin\CmsContactController;
use Rateb\App\Controllers\Admin\CmsDashboardController;
use Rateb\App\Controllers\Admin\CmsFaqCategoriesController;
use Rateb\App\Controllers\Admin\CmsFaqsController;
use Rateb\App\Controllers\Admin\CmsFooterColumnsController;
use Rateb\App\Controllers\Admin\CmsHelpArticlesController;
use Rateb\App\Controllers\Admin\CmsKbArticlesController;
use Rateb\App\Controllers\Admin\CmsLeadsController;
use Rateb\App\Controllers\Admin\CmsMediaController;
use Rateb\App\Controllers\Admin\CmsMenuItemsController;
use Rateb\App\Controllers\Admin\CmsNewsletterController;
use Rateb\App\Controllers\Admin\CmsOfficesController;
use Rateb\App\Controllers\Admin\CmsPageBuilderController;
use Rateb\App\Controllers\Admin\CmsPagesController;
use Rateb\App\Controllers\Admin\CmsPartnersController;
use Rateb\App\Controllers\Admin\CmsRedirectsController;
use Rateb\App\Controllers\Admin\CmsRobotsController;
use Rateb\App\Controllers\Admin\CmsSectionsController;
use Rateb\App\Controllers\Admin\CmsSeoController;
use Rateb\App\Controllers\Admin\CmsServiceCategoriesController;
use Rateb\App\Controllers\Admin\CmsServicesController;
use Rateb\App\Controllers\Admin\CmsSlidesController;
use Rateb\App\Controllers\Admin\CmsSystemStatusController;
use Rateb\App\Controllers\Admin\CmsTeamMembersController;
use Rateb\App\Controllers\Admin\CmsTestimonialsController;
use Rateb\App\Controllers\Admin\CmsThemeController;
use Rateb\App\Controllers\Admin\CmsTimelineController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$router->get('/admin/cms', [CmsDashboardController::class, 'index'], rateb_admin_mw('cms.view'));

$cmsCrud = [
    'pages' => [CmsPagesController::class, 'cms.manage'],
    'sections' => [CmsSectionsController::class, 'cms.manage'],
    'blocks' => [CmsBlocksController::class, 'cms.manage'],
    'menu-items' => [CmsMenuItemsController::class, 'cms.manage'],
    'blog-articles' => [CmsBlogArticlesController::class, 'cms.manage'],
    'blog-categories' => [CmsBlogCategoriesController::class, 'cms.manage'],
    'blog-tags' => [CmsBlogTagsController::class, 'cms.manage'],
    'blog-authors' => [CmsBlogAuthorsController::class, 'cms.manage'],
    'faq-categories' => [CmsFaqCategoriesController::class, 'cms.manage'],
    'faqs' => [CmsFaqsController::class, 'cms.manage'],
    'testimonials' => [CmsTestimonialsController::class, 'cms.manage'],
    'slides' => [CmsSlidesController::class, 'cms.manage'],
    'service-categories' => [CmsServiceCategoriesController::class, 'cms.manage'],
    'services' => [CmsServicesController::class, 'cms.manage'],
    'partners' => [CmsPartnersController::class, 'cms.manage'],
    'careers' => [CmsCareersController::class, 'cms.manage'],
    'kb-articles' => [CmsKbArticlesController::class, 'cms.manage'],
    'help-articles' => [CmsHelpArticlesController::class, 'cms.manage'],
    'system-status' => [CmsSystemStatusController::class, 'cms.manage'],
    'team' => [CmsTeamMembersController::class, 'cms.manage'],
    'timeline' => [CmsTimelineController::class, 'cms.manage'],
    'offices' => [CmsOfficesController::class, 'cms.manage'],
    'footer-columns' => [CmsFooterColumnsController::class, 'cms.manage'],
    'seo' => [CmsSeoController::class, 'cms.seo'],
    'redirects' => [CmsRedirectsController::class, 'cms.seo'],
    'newsletter' => [CmsNewsletterController::class, 'cms.manage'],
];

foreach ($cmsCrud as $path => [$class, $perm]) {
    $router->get('/admin/cms/' . $path, [$class, 'index'], rateb_admin_mw($perm));
    $router->get('/admin/cms/' . $path . '/create', [$class, 'create'], rateb_admin_mw($perm));
    $router->post('/admin/cms/' . $path, [$class, 'store'], rateb_admin_mw($perm));
    $router->get('/admin/cms/' . $path . '/{id}/edit', [$class, 'edit'], rateb_admin_mw($perm));
    $router->post('/admin/cms/' . $path . '/{id}', [$class, 'update'], rateb_admin_mw($perm));
    $router->post('/admin/cms/' . $path . '/{id}/delete', [$class, 'destroy'], rateb_admin_mw($perm));
    $router->post('/admin/cms/' . $path . '/bulk-delete', [$class, 'bulkDestroy'], rateb_admin_mw($perm));
    $router->get('/admin/cms/' . $path . '/{id}/documents', [$class, 'documents'], rateb_admin_mw($perm));
    $router->post('/admin/cms/' . $path . '/{id}/documents', [$class, 'storeDocument'], rateb_admin_mw($perm));
}

$router->get('/admin/cms/leads', [CmsLeadsController::class, 'index'], rateb_admin_mw('cms.leads'));
$router->get('/admin/cms/leads/{id}', [CmsLeadsController::class, 'show'], rateb_admin_mw('cms.leads'));
$router->post('/admin/cms/leads/{id}', [CmsLeadsController::class, 'update'], rateb_admin_mw('cms.leads'));

$router->get('/admin/cms/media', [CmsMediaController::class, 'index'], rateb_admin_mw('cms.media'));
$router->get('/admin/cms/media/json', [CmsMediaController::class, 'listJson'], rateb_admin_mw('cms.media'));
$router->post('/admin/cms/media/upload', [CmsMediaController::class, 'upload'], rateb_admin_mw('cms.media'));
$router->post('/admin/cms/media/tinymce-upload', [CmsMediaController::class, 'tinymceUpload'], rateb_admin_mw('cms.media'));

$router->get('/admin/cms/theme', [CmsThemeController::class, 'index'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/theme', [CmsThemeController::class, 'save'], rateb_admin_mw('cms.manage'));

$router->get('/admin/cms/analytics', [CmsAnalyticsController::class, 'index'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/analytics', [CmsAnalyticsController::class, 'save'], rateb_admin_mw('cms.manage'));

$router->get('/admin/cms/robots', [CmsRobotsController::class, 'index'], rateb_admin_mw('cms.seo'));
$router->post('/admin/cms/robots', [CmsRobotsController::class, 'save'], rateb_admin_mw('cms.seo'));

$router->get('/admin/cms/about', [CmsAboutController::class, 'index'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/about', [CmsAboutController::class, 'save'], rateb_admin_mw('cms.manage'));

$router->get('/admin/cms/contact', [CmsContactController::class, 'index'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/contact', [CmsContactController::class, 'save'], rateb_admin_mw('cms.manage'));

$router->get('/admin/cms/newsletter/export', [CmsNewsletterController::class, 'export'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/newsletter/import', [CmsNewsletterController::class, 'import'], rateb_admin_mw('cms.manage'));
$router->get('/admin/cms/newsletter/campaign', [CmsNewsletterController::class, 'campaignForm'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/newsletter/campaign/save', [CmsNewsletterController::class, 'campaignSave'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/newsletter/campaign/send', [CmsNewsletterController::class, 'campaignSend'], rateb_admin_mw('cms.manage'));

$router->get('/admin/cms/page-builder', [CmsPageBuilderController::class, 'index'], rateb_admin_mw('cms.manage'));
$router->post('/admin/cms/page-builder/reorder', [CmsPageBuilderController::class, 'reorder'], rateb_admin_mw('cms.manage'));
