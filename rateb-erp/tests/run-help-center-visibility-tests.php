<?php
declare(strict_types=1);

/**
 * Help Center: tenant modules visible to everyone; Admin oversight on platform only.
 * Run: php rateb-erp/tests/run-help-center-visibility-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\Help\HelpPermissionGate;

$passed = 0;
$failed = 0;

function hc_assert(bool $cond, string $label): void
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

$modulesPath = $root . '/config/help-center/modules.php';
$blueprintsPath = $root . '/config/help-center/blueprints.php';
$gatePath = $root . '/app/services/Help/HelpPermissionGate.php';

/** @var list<array<string,mixed>> $modules */
$modules = require $modulesPath;
/** @var array<string,array<string,mixed>> $blueprints */
$blueprints = require $blueprintsPath;
$gateSrc = (string) file_get_contents($gatePath);

$bySlug = [];
foreach ($modules as $module) {
    $slug = (string) ($module['slug'] ?? '');
    if ($slug !== '') {
        $bySlug[$slug] = $module;
    }
}

hc_assert(isset($bySlug['admin-oversight']), 'admin-oversight catalog exists');
hc_assert(($bySlug['admin-oversight']['host'] ?? '') === 'platform', 'admin-oversight host is platform');
hc_assert(!empty($bySlug['admin-oversight']['requires_super_admin']), 'admin-oversight requires super admin');
hc_assert(isset($blueprints['admin-oversight']), 'admin-oversight blueprints exist');
hc_assert(count($blueprints['admin-oversight']['articles'] ?? []) >= 4, 'admin-oversight has articles');

$platformOnly = [];
foreach ($modules as $module) {
    if (strtolower((string) ($module['host'] ?? 'all')) === 'platform') {
        $platformOnly[] = (string) ($module['slug'] ?? '');
    }
}
hc_assert($platformOnly === ['admin-oversight'], 'only admin-oversight is platform-host help');

$tenantExpected = [
    'dashboard', 'sales-screen', 'branches', 'purchases', 'inventory', 'pos',
    'logistics', 'suppliers', 'hr', 'recruitment', 'crm', 'marketplace', 'projects',
    'approvals', 'manufacturing', 'payroll', 'quality', 'bi', 'accounting',
    'contracts-assets', 'access-control', 'notifications', 'profile', 'settings-support', 'website',
];
foreach ($tenantExpected as $slug) {
    hc_assert(isset($bySlug[$slug]), "tenant help module exists: {$slug}");
    hc_assert(strtolower((string) ($bySlug[$slug]['host'] ?? 'all')) !== 'platform', "{$slug} is not platform-only");
    hc_assert(isset($blueprints[$slug]), "blueprint exists: {$slug}");
}

hc_assert(!str_contains($gateSrc, "rateb_nav_can('',"), 'gate no longer uses empty-permission nav can');
hc_assert(str_contains($gateSrc, 'companyHasModule'), 'gate uses company plan module pack');
hc_assert(str_contains($gateSrc, 'canSeeHost'), 'gate has host filter');
hc_assert(str_contains($gateSrc, 'canSeeCatalogModule'), 'gate has catalog-row filter');

$gate = new HelpPermissionGate();
hc_assert($gate->canSeeHost('all') === true, 'host all is visible');
hc_assert($gate->canSeeHost('') === true, 'empty host is visible');
hc_assert($gate->canSeeModule(null) === true, 'empty module gate is visible');
hc_assert($gate->canSeeModule('') === true, 'blank module gate is visible');

$oversight = $bySlug['admin-oversight'];
$purchases = $bySlug['purchases'];
$wasSa = !empty($_SESSION['rateb_is_super_admin']);
$_SESSION['rateb_is_super_admin'] = false;

$notSa = $gate->canSeeCatalogModule($oversight);
hc_assert($notSa === false, 'non-SA cannot see admin-oversight catalog');

$_SESSION['rateb_is_super_admin'] = $wasSa;

$purchasesVisibleToPlan = $gate->canSeeAudience((string) ($purchases['audience'] ?? 'all'));
hc_assert($purchasesVisibleToPlan === true, 'purchases audience is all');

$js = (string) file_get_contents($root . '/public/assets/js/help-center.js');
$css = (string) file_get_contents($root . '/public/assets/css/help-center.css');
$indexView = (string) file_get_contents($root . '/views/help/index.php');
$layout = (string) file_get_contents($root . '/views/layouts/main.php');

hc_assert(str_contains($js, "rateb:nav:afterEnter"), 'help search rebinds after soft-nav');
hc_assert(str_contains($js, 'fetchRemote'), 'help search fetches as you type');
hc_assert(str_contains($css, 'overflow: visible'), 'search dropdown is not clipped by hero overflow');
hc_assert(str_contains($indexView, 'data-hc-search-url'), 'help home exposes live search URL');
hc_assert(str_contains($layout, "js/help-center.js"), 'layout always loads help-center.js');

echo "\nHelp Center visibility tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
