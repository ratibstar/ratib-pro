<?php
declare(strict_types=1);

/**
 * In-app Help Center + AI Help Assistant routes (authenticated ERP users).
 */

use Rateb\App\Controllers\Admin\HelpCenterAdminController;
use Rateb\App\Controllers\Shared\HelpAssistantController;
use Rateb\App\Controllers\Shared\HelpCenterController;
use Rateb\App\Core\Middleware\ErpAuthMiddleware;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$auth = [ErpAuthMiddleware::class];

$router->get('/admin/help', [HelpCenterController::class, 'index'], $auth);
$router->get('/admin/help/module/{slug}', [HelpCenterController::class, 'module'], $auth);
$router->get('/admin/help/article/{slug}', [HelpCenterController::class, 'article'], $auth);
$router->get('/admin/help/api/search', [HelpCenterController::class, 'searchApi'], $auth);
$router->get('/admin/help/api/context', [HelpCenterController::class, 'contextApi'], $auth);
$router->get('/admin/help/api/index', [HelpCenterController::class, 'indexJson'], $auth);

// AI Help Assistant chat API
$router->get('/admin/help/assistant/bootstrap', [HelpAssistantController::class, 'bootstrap'], $auth);
$router->post('/admin/help/assistant/ask', [HelpAssistantController::class, 'ask'], $auth);
$router->post('/admin/help/assistant/track', [HelpAssistantController::class, 'track'], $auth);

// Content management + analytics (gated in controller)
$router->get('/admin/help/manage', [HelpCenterAdminController::class, 'index'], $auth);
$router->get('/admin/help/manage/create', [HelpCenterAdminController::class, 'create'], $auth);
$router->post('/admin/help/manage/store', [HelpCenterAdminController::class, 'store'], $auth);
$router->get('/admin/help/manage/edit/{id}', [HelpCenterAdminController::class, 'edit'], $auth);
$router->post('/admin/help/manage/update/{id}', [HelpCenterAdminController::class, 'update'], $auth);
$router->post('/admin/help/manage/archive/{id}', [HelpCenterAdminController::class, 'archive'], $auth);
$router->get('/admin/help/manage/analytics', [HelpCenterAdminController::class, 'analytics'], $auth);
$router->post('/admin/help/manage/unanswered/{id}', [HelpCenterAdminController::class, 'resolveUnanswered'], $auth);
