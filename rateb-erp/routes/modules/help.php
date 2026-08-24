<?php
declare(strict_types=1);

/**
 * In-app Help Center routes (authenticated ERP users).
 */

use Rateb\App\Controllers\Admin\HelpCenterAdminController;
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

// Content management (gated in controller: Super Admin / help.manage)
$router->get('/admin/help/manage', [HelpCenterAdminController::class, 'index'], $auth);
