<?php
declare(strict_types=1);

/**
 * Automotive Phase 1 — module gate, permissions, routes. No database writes.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-phase1-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

require_once dirname(__DIR__) . '/AutomotiveModule.php';
\Rateb\App\Automotive\AutomotiveModule::init();

use Rateb\App\Automotive\AutomotiveModule;
use Rateb\App\Automotive\Controllers\AutomotiveDashboardController;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;

function auto1_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        echo "PASS: {$label}\n";
        $passed++;
        return;
    }
    echo "FAIL: {$label}\n";
    $failed++;
}

auto1_assert(class_exists(AutomotiveModule::class), 'AutomotiveModule loads');
auto1_assert(class_exists(AutomotiveDashboardController::class), 'dashboard controller autoloads');

$perms = AutomotiveModule::entityPermissions();
auto1_assert(($perms['automotive']['module'] ?? '') === 'automotive', 'entity module is automotive');
auto1_assert(($perms['automotive']['view'] ?? '') === 'automotive.view', 'entity view slug');
auto1_assert(($perms['automotive']['manage'] ?? '') === 'automotive.manage', 'entity manage slug');

$merged = rateb_entity_perms('automotive');
auto1_assert(($merged['module'] ?? '') === 'automotive', 'runtime entity map module');
auto1_assert(($merged['view'] ?? '') === 'automotive.view', 'runtime entity map view');

$catalog = PlanLimitService::moduleCatalog();
auto1_assert(isset($catalog['automotive']), 'automotive is in the company module catalog');
auto1_assert(($catalog['automotive'] ?? '') === 'automotive_platform', 'catalog label key');

$known = PlanLimitService::filterKnownModules(['automotive', 'not-a-module']);
auto1_assert($known === ['automotive'], 'filterKnownModules keeps automotive only');

$modulePerms = require $root . '/config/module-permissions.php';
auto1_assert(($modulePerms['automotive'] ?? '') === 'automotive.manage', 'module permission default is manage');

$sys = require $root . '/config/permissions-system.php';
$implies = $sys['permission_implies']['automotive.manage'] ?? [];
auto1_assert($implies === ['automotive.view'], 'manage implies view only');

$tiers = require $root . '/config/plan-tiers.php';
foreach (['professional', 'enterprise', 'starter', 'growth'] as $tier) {
    $mods = $tiers[$tier]['modules'] ?? null;
    if (!is_array($mods)) {
        continue;
    }
    auto1_assert(!in_array('automotive', $mods, true), $tier . ' plan does not enable automotive by default');
}

$addons = require $root . '/config/module-addons.php';
auto1_assert(isset($addons['automotive']), 'addon catalog lists automotive');
auto1_assert(($addons['automotive']['enabled'] ?? true) === false, 'addon is not for sale');
auto1_assert((float) ($addons['automotive']['monthly'] ?? 1) === 0.0, 'addon monthly price is zero');

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/automotive.php');
auto1_assert(str_contains($routes, "rateb_erp_mw('automotive'"), 'routes use automotive module middleware');
auto1_assert(str_contains($routes, 'AutomotiveDashboardController'), 'routes register dashboard');
auto1_assert(!str_contains($routes, 'rateb_inventory'), 'routes do not touch inventory');

$manifest = require $root . '/routes/manifest.php';
auto1_assert(in_array('automotive', array_column($manifest, 'id'), true), 'route manifest includes automotive');

$sidebar = (string) file_get_contents(dirname(__DIR__) . '/views/partials/sidebar-automotive-nav.php');
auto1_assert(str_contains($sidebar, "'automotive', 'automotive.view'"), 'sidebar requires module and view permission');

$ops = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
auto1_assert(str_contains($ops, 'sidebar-automotive-nav.php'), 'ops nav includes automotive partial');

$migration = (string) file_get_contents($root . '/migrations/271_automotive_permissions.sql');
auto1_assert(str_contains($migration, 'automotive.view') && str_contains($migration, 'automotive.manage'), 'permission migration registers both slugs');
auto1_assert(!str_contains($migration, 'rateb_inventory'), 'permission migration does not alter inventory');
auto1_assert(!preg_match('/CREATE TABLE/i', $migration), 'permission migration creates no tables');

echo "\nAutomotive Phase 1: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
