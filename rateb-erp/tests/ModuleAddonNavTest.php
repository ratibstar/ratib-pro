<?php
declare(strict_types=1);

/**
 * Phase 5 — locked module navigation (sidebar only).
 * Run: php rateb-erp/tests/ModuleAddonNavTest.php
 */

$root = dirname(__DIR__);
if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

$passed = 0;
$failed = 0;
$skipped = 0;

function mac5_assert(bool $cond, string $label): void
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

$nav = (string) file_get_contents($root . '/views/partials/sidebar-nav.php');
$hr = (string) file_get_contents($root . '/views/partials/sidebar-hr-nav.php');
$ops = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
$mw = (string) file_get_contents($root . '/app/Core/Middleware/Middleware.php');
$pls = (string) file_get_contents($root . '/app/services/PlanLimitService.php');
$app = (string) file_get_contents($root . '/config/app.php');

mac5_assert(str_contains($nav, 'MODULE_ADDON_COMMERCE_ENABLED') || str_contains($nav, 'ModuleAddonService::FLAG_NAME'), 'sidebar reads existing feature flag');
mac5_assert(str_contains($nav, 'rateb_is_super_admin'), 'super admin does not get purchase links without company commerce context');
mac5_assert(str_contains($nav, 'rateb_nav_tenant_company_id_for_gate'), 'session/tenant company is used, not request company_id');
mac5_assert(str_contains($nav, 'rateb_can($permission)'), 'locked items require existing RBAC');
mac5_assert(str_contains($nav, 'isPurchasable($slug)'), 'locked items require catalog isPurchasable');
mac5_assert(str_contains($nav, 'companyHasModule($ctx[\'company_id\'], $slug)'), 'locked items use existing PlanLimitService::companyHasModule');
mac5_assert(str_contains($nav, "admin/billing/modules/' . \$module"), 'locked href is billing checkout, not ops runtime');
mac5_assert(!str_contains($nav, 'admin/ops/{slug}') && !str_contains($nav, "admin/ops/' . \$module"), 'locked items do not use runtime ops URLs');
mac5_assert(!str_contains($nav, 'company_id='), 'sidebar does not pass company_id in the URL');
mac5_assert(!str_contains($nav, 'price='), 'sidebar does not pass price in the URL');
mac5_assert(!preg_match('/style\s*=/', $nav) && !preg_match('/style\s*=/', $hr), 'no inline CSS');
mac5_assert(!preg_match('/<script/i', $nav) && !preg_match('/<script/i', $hr), 'no inline JS');
mac5_assert(!preg_match('/\b(49|441)\b/', $nav), 'no hard-coded prices');
mac5_assert(str_contains($nav, 'fa-lock'), 'lock icon is visible');
mac5_assert(str_contains($nav, 'aria-label'), 'locked links are accessible');
mac5_assert(str_contains($nav, 'rateb_nav_can($permission, $module)'), 'accessible modules still use rateb_nav_can');
mac5_assert(str_contains($nav, 'addonLockedRendered'), 'one locked item per module slug');
mac5_assert(!str_contains($nav, 'activateFromPaidInvoice') && !str_contains($nav, 'expireDueAddons'), 'sidebar is read-only');
mac5_assert(!str_contains($nav, 'updateModules') && !str_contains($hr, 'updateModules'), 'sidebar does not write company.modules');

mac5_assert(str_contains($hr, "admin/billing/modules/hr"), 'locked HR goes to billing/modules/hr');
mac5_assert(str_contains($hr, 'rateb_nav_can($hrPerm, \'hr\')'), 'enabled HR keeps existing rateb_nav_can gate');
mac5_assert(str_contains($hr, 'isLockedPurchasableModule(\'hr\''), 'HR locked state uses the same local helper');
mac5_assert(str_contains($hr, 'fa-lock'), 'HR locked item shows a lock');
mac5_assert(str_contains($hr, 'config/hr-menu.php'), 'enabled HR still loads the existing HR tree');

mac5_assert(str_contains($ops, "require RATEB_ROOT . '/views/partials/sidebar-hr-nav.php'"), 'HR still included from ops nav');
mac5_assert(!str_contains($ops, 'billing/modules'), 'ops nav partial itself is unchanged');

mac5_assert(
    str_contains($app, 'function rateb_nav_can') && str_contains($app, 'companyHasModule($companyId, $module)'),
    'rateb_nav_can still gates on company modules'
);
mac5_assert(str_contains($mw, 'final class CompanyModuleMiddleware'), 'CompanyModuleMiddleware remains in Middleware.php');
mac5_assert(str_contains($pls, 'function companyHasModule'), 'PlanLimitService companyHasModule unchanged as the access reader');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/services/AuthorizationService.php',
    'config/app.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/Payment/Gateways/MoyasarGateway.php',
    'app/controllers/Api/PaymentWebhookController.php',
    'app/services/CronService.php',
    'bin/erp-cron.php',
    'routes/manifest.php',
    'app/services/AgencyErpMigrationService.php',
    'app/services/SaaSAutomationService.php',
    'app/services/ModuleAddonService.php',
    'app/services/ModuleAddonCheckoutService.php',
    'app/services/ModuleAddonActivationHook.php',
];
foreach ($frozen as $rel) {
    mac5_assert(is_file($root . '/' . $rel), 'frozen present ' . $rel);
}

echo "\nModule addon nav tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
