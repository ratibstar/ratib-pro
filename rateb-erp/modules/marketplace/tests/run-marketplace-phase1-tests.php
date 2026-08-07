<?php
declare(strict_types=1);

/**
 * Marketplace Phase 1 — foundation tests (structure / RBAC / migrations).
 * Run: php rateb-erp/modules/marketplace/tests/run-marketplace-phase1-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

require_once dirname(__DIR__) . '/MarketplaceModule.php';
\Rateb\App\Marketplace\MarketplaceModule::init();

use Rateb\App\Marketplace\Controllers\MarketplaceDashboardController;
use Rateb\App\Marketplace\Controllers\MarketplaceProvidersController;
use Rateb\App\Marketplace\Controllers\MarketplaceServicesController;
use Rateb\App\Marketplace\MarketplaceModule;
use Rateb\App\Marketplace\Models\MarketplaceProvider;
use Rateb\App\Marketplace\Models\MarketplaceService;
use Rateb\App\Marketplace\Repositories\MarketplaceProviderRepository;
use Rateb\App\Marketplace\Services\MarketplaceDashboardService;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;

function mp1_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

mp1_assert(class_exists(MarketplaceModule::class), 'MarketplaceModule class loads');
mp1_assert(is_dir(MarketplaceModule::rootPath()), 'module root path exists');
mp1_assert(class_exists(MarketplaceDashboardController::class), 'Dashboard controller autoload');
mp1_assert(class_exists(MarketplaceProvidersController::class), 'Providers controller autoload');
mp1_assert(class_exists(MarketplaceServicesController::class), 'Services controller autoload');
mp1_assert(class_exists(MarketplaceProvider::class), 'MarketplaceProvider model bundle');
mp1_assert(class_exists(MarketplaceService::class), 'MarketplaceService model bundle');
mp1_assert(class_exists(MarketplaceProviderRepository::class), 'Provider repository');
mp1_assert(class_exists(MarketplaceDashboardService::class), 'Dashboard service');

$perms = MarketplaceModule::entityPermissions();
mp1_assert(($perms['marketplace']['module'] ?? '') === 'marketplace', 'entity map module=marketplace');
mp1_assert(($perms['marketplace']['view'] ?? '') === 'marketplace.view', 'entity view slug');
mp1_assert(($perms['marketplace']['manage'] ?? '') === 'marketplace.manage', 'entity manage slug');

$catalog = PlanLimitService::moduleCatalog();
mp1_assert(isset($catalog['marketplace']), 'marketplace in company_modules catalog');
mp1_assert(($catalog['marketplace'] ?? '') === 'marketplace_platform', 'tenant label key marketplace_platform');

$known = PlanLimitService::filterKnownModules(['marketplace', 'not-a-module', 'crm']);
mp1_assert(in_array('marketplace', $known, true), 'filterKnownModules accepts marketplace');
mp1_assert(!in_array('not-a-module', $known, true), 'filterKnownModules rejects unknown');

$modulePerms = require $root . '/config/module-permissions.php';
mp1_assert(($modulePerms['marketplace'] ?? '') === 'marketplace.manage', 'module-permissions default slug');

$sys = require $root . '/config/permissions-system.php';
$implies = $sys['permission_implies']['marketplace.manage'] ?? [];
mp1_assert(in_array('marketplace.view', $implies, true), 'marketplace.manage implies view');

$tiers = require $root . '/config/plan-tiers.php';
mp1_assert(in_array('marketplace', $tiers['professional']['modules'] ?? [], true), 'professional plan has marketplace');
mp1_assert(in_array('marketplace', $tiers['enterprise']['modules'] ?? [], true), 'enterprise plan has marketplace');

$migration = $root . '/migrations/229_marketplace_foundation.sql';
mp1_assert(is_file($migration), 'migration 229 exists');
$sql = (string) file_get_contents($migration);
foreach ([
    'rateb_mp_providers',
    'rateb_mp_categories',
    'rateb_mp_services',
    'rateb_mp_service_availability',
    'rateb_mp_service_requests',
    'rateb_mp_orders',
    'rateb_mp_order_items',
    'rateb_mp_reviews',
    'rateb_mp_timeline',
] as $table) {
    mp1_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table), 'creates ' . $table);
}
mp1_assert(!preg_match('/\b(DROP|TRUNCATE)\b/i', $sql), 'migration has no DROP/TRUNCATE');
mp1_assert(!preg_match('/\bALTER TABLE\b/i', $sql), 'migration has no ALTER TABLE');
mp1_assert(str_contains($sql, 'company_id'), 'tables are tenant-scoped');

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/marketplace.php');
mp1_assert(str_contains($routes, 'MarketplaceDashboardController'), 'routes register dashboard');
mp1_assert(str_contains($routes, 'MarketplaceProvidersController'), 'routes register providers');
mp1_assert(!str_contains($routes, '/api/'), 'Phase 1 routes have no API paths');
mp1_assert(!str_contains($routes, '/site/customer'), 'Phase 1 routes have no customer portal paths');

mp1_assert(is_file(MarketplaceModule::viewsPath() . '/dashboard/index.php'), 'dashboard view exists');
mp1_assert(is_file(MarketplaceModule::viewsPath() . '/partials/sidebar-marketplace-nav.php'), 'sidebar partial exists');

$manifest = require $root . '/routes/manifest.php';
$ids = array_column($manifest, 'id');
mp1_assert(in_array('marketplace', $ids, true), 'manifest includes marketplace');

$stats = (new MarketplaceDashboardService())->placeholderStats(1);
mp1_assert(($stats['providers'] ?? -1) === 0, 'placeholder providers=0');

echo "\nMarketplace Phase 1 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
