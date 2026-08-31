<?php
declare(strict_types=1);

/**
 * Module Add-on Commerce checkout (Phase 2).
 * Empty module argument: authentication/company SaaS only — no module entitlement middleware.
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Controllers\Company\ModuleAddonCheckoutController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$addonMw = rateb_erp_mw();

$router->get('/admin/billing/modules/{slug}/status', [ModuleAddonCheckoutController::class, 'status'], $addonMw);
$router->get('/admin/billing/modules/{slug}', [ModuleAddonCheckoutController::class, 'show'], $addonMw);
$router->post('/admin/billing/modules/{slug}', [ModuleAddonCheckoutController::class, 'purchase'], $addonMw);
