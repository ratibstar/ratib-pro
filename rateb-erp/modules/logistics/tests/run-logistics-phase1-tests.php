<?php
declare(strict_types=1);

/**
 * Logistics Phase 1 — foundation tests (no DB required for enable/disable catalog checks).
 * Run: php rateb-erp/modules/logistics/tests/run-logistics-phase1-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

require_once dirname(__DIR__) . '/LogisticsModule.php';
\Rateb\App\Logistics\LogisticsModule::init();

use Rateb\App\Logistics\LogisticsModule;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;

function logistics_assert(bool $cond, string $label): void
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

logistics_assert(class_exists(LogisticsModule::class), 'LogisticsModule class loads');
logistics_assert(is_dir(LogisticsModule::rootPath()), 'module root path exists');

$perms = LogisticsModule::entityPermissions();
logistics_assert(isset($perms['logistics']['module']) && $perms['logistics']['module'] === 'logistics', 'entity map module=logistics');
logistics_assert(($perms['logistics']['view'] ?? '') === 'logistics.view', 'entity view slug');
logistics_assert(($perms['logistics']['manage'] ?? '') === 'logistics.manage', 'entity manage slug');
logistics_assert(($perms['logistics/shipments']['post'] ?? '') === 'logistics.dispatch', 'dispatch on shipments');
logistics_assert(($perms['logistics/expenses']['manage'] ?? '') === 'logistics.expense', 'expense permission mapping');
logistics_assert(($perms['logistics/reports']['view'] ?? '') === 'logistics.report', 'report permission mapping');

$catalog = PlanLimitService::moduleCatalog();
logistics_assert(isset($catalog['logistics']), 'logistics registered in company_modules catalog');
logistics_assert(($catalog['logistics'] ?? '') === 'logistics_platform', 'tenant label key logistics_platform');

$known = PlanLimitService::filterKnownModules(['logistics', 'not-a-module', 'inventory']);
logistics_assert(in_array('logistics', $known, true), 'filterKnownModules accepts logistics (enable path)');
logistics_assert(!in_array('not-a-module', $known, true), 'filterKnownModules rejects unknown module');

$modulePerms = require $root . '/config/module-permissions.php';
logistics_assert(($modulePerms['logistics'] ?? '') === 'logistics.manage', 'module-permissions default slug');

$sys = require $root . '/config/permissions-system.php';
$implies = $sys['permission_implies']['logistics.manage'] ?? [];
logistics_assert(in_array('logistics.view', $implies, true), 'logistics.manage implies view');
logistics_assert(in_array('logistics.dispatch', $implies, true), 'logistics.manage implies dispatch');
logistics_assert(in_array('logistics.driver', $implies, true), 'logistics.manage implies driver');
logistics_assert(in_array('logistics.expense', $implies, true), 'logistics.manage implies expense');
logistics_assert(in_array('logistics.report', $implies, true), 'logistics.manage implies report');

$migration = $root . '/migrations/224_logistics_foundation.sql';
logistics_assert(is_file($migration), 'migration 224 exists');
$sql = (string) file_get_contents($migration);
foreach ([
    'rateb_logistics_drivers',
    'rateb_logistics_vehicles',
    'rateb_logistics_routes',
    'rateb_logistics_delivery_orders',
    'rateb_logistics_trips',
    'rateb_logistics_shipments',
    'rateb_logistics_delivery_proofs',
    'rateb_logistics_expenses',
] as $table) {
    logistics_assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table), 'creates ' . $table);
}
logistics_assert(!preg_match('/\b(DROP|TRUNCATE)\b/i', $sql), 'migration has no DROP/TRUNCATE');
logistics_assert(!preg_match('/\bALTER TABLE\b/i', $sql), 'migration has no ALTER TABLE');
foreach ([
    'logistics.view',
    'logistics.manage',
    'logistics.dispatch',
    'logistics.driver',
    'logistics.expense',
    'logistics.report',
] as $slug) {
    logistics_assert(str_contains($sql, "'" . $slug . "'"), 'permission seeded: ' . $slug);
}

$en = require dirname(__DIR__) . '/config/lang/en.php';
$ar = require dirname(__DIR__) . '/config/lang/ar.php';
logistics_assert(($en['logistics_platform'] ?? '') !== '', 'en logistics_platform');
logistics_assert(($ar['logistics_platform'] ?? '') !== '', 'ar logistics_platform');

$manifest = require $root . '/routes/manifest.php';
$manifestIds = array_map(static fn(array $m): string => (string) ($m['id'] ?? ''), $manifest);
logistics_assert(in_array('logistics', $manifestIds, true), 'manifest registers logistics');

echo "\nLogistics Phase 1 tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
