<?php
declare(strict_types=1);

/**
 * Marketplace Admin routes — Phase 1 shell only (no API / portal / workflow).
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Marketplace\Controllers\MarketplaceDashboardController;
use Rateb\App\Marketplace\Controllers\MarketplaceProvidersController;
use Rateb\App\Marketplace\Controllers\MarketplaceServicesController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$mpApp = static fn (string $sub = '') => '/' . rateb_app_route($sub === '' ? 'marketplace' : 'marketplace/' . ltrim($sub, '/'));
$mpMw = static fn (string $entity = 'marketplace') => rateb_erp_mw('marketplace', '', $entity);

$router->get($mpApp(''), [MarketplaceDashboardController::class, 'index'], $mpMw());
$router->get($mpApp('dashboard'), [MarketplaceDashboardController::class, 'index'], $mpMw());
$router->get($mpApp('providers'), [MarketplaceProvidersController::class, 'index'], $mpMw('marketplace/providers'));
$router->get($mpApp('services'), [MarketplaceServicesController::class, 'index'], $mpMw('marketplace/services'));
