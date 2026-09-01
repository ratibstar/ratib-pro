<?php
declare(strict_types=1);

/**
 * Demo-host preview user bootstrap (admin.rateb.sa only).
 * Run: php rateb-erp/tests/ModuleAddonDemoPreviewTest.php
 */

$root = dirname(__DIR__);
if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\ModuleAddonDemoPreviewService;
use Rateb\App\Services\ModuleAddonService;

$passed = 0;
$failed = 0;

function macd_assert(bool $cond, string $label): void
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

function macd_set_env(string $name, ?string $value): void
{
    if ($value === null || $value === '') {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

$kept = ModuleAddonDemoPreviewService::modulesWithoutSlug(
    ['dashboard', 'crm', 'inventory', 'crm', ''],
    'crm'
);
macd_assert($kept === ['dashboard', 'inventory', 'notifications'], 'modulesWithoutSlug drops crm and keeps implied');
macd_assert(!in_array('crm', $kept, true), 'crm is absent after strip');

$src = (string) file_get_contents($root . '/app/services/ModuleAddonDemoPreviewService.php');
$ctrl = (string) file_get_contents($root . '/app/controllers/Company/ModuleAddonDemoPreviewController.php');
$routes = (string) file_get_contents($root . '/routes/modules/module-addons.php');
$view = (string) file_get_contents($root . '/views/billing/module-demo-preview.php');

macd_assert(str_contains($src, 'previewDemoHostAllowed'), 'service requires demo host guard');
macd_assert(str_contains($src, 'updateModules'), 'service writes company.modules via Company model');
macd_assert(!str_contains($src, 'PaymentService') && !str_contains($ctrl, 'PaymentService'), 'no PaymentService');
macd_assert(!str_contains($src, 'Moyasar') && !str_contains($ctrl, 'Moyasar'), 'no Moyasar');
macd_assert(!str_contains($src, 'startCheckout') && !str_contains($ctrl, 'startCheckout'), 'does not start checkout');
macd_assert(!str_contains($src, 'activateFromPaidInvoice'), 'does not activate CRM add-on');
macd_assert(str_contains($src, 'is_super_admin') && str_contains($src, '0'), 'demo user is not super admin');
macd_assert(str_contains($src, 'crm.view'), 'demo role includes crm.view');
macd_assert(str_contains($routes, '/admin/billing/addon-preview-user'), 'preview route registered before slug routes');
macd_assert(str_contains($routes, 'rateb_admin_mw()'), 'preview route is Super Admin only');
macd_assert(strpos($routes, 'addon-preview-user') < strpos($routes, '/admin/billing/modules/{slug}'), 'preview route is not captured as a slug');
macd_assert(str_contains($ctrl, 'previewDemoHostAllowed'), 'controller 404s off demo host');
macd_assert(str_contains($view, 'demo-preview-password'), 'password is shown once after bootstrap');
macd_assert(!str_contains($view, 'Purchase'), 'preview bootstrap is not the purchase form');

$savedHost = $_SERVER['HTTP_HOST'] ?? null;
macd_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, '1');
macd_set_env('RATEB_ENV', 'staging');
macd_set_env(ModuleAddonService::FLAG_NAME, '1');
$_SERVER['HTTP_HOST'] = 'admin.rateb.sa';
macd_assert((new ModuleAddonService())->previewDemoHostAllowed() === true, 'demo host allowed when preview flags set');
$_SERVER['HTTP_HOST'] = 'rateb.sa';
macd_assert((new ModuleAddonService())->previewDemoHostAllowed() === false, 'production host refused');
$_SERVER['HTTP_HOST'] = 'foo.rateb.sa';
macd_assert((new ModuleAddonService())->previewDemoHostAllowed() === false, 'other agency host refused');
macd_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, null);
macd_set_env('RATEB_ENV', null);
macd_set_env(ModuleAddonService::FLAG_NAME, null);
if ($savedHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $savedHost;
}

$blocked = (new ModuleAddonDemoPreviewService())->ensureDemoUser('PreviewPass#1');
macd_assert(($blocked['ok'] ?? true) === false, 'ensureDemoUser refuses when not on demo host');
macd_assert(($blocked['code'] ?? '') === 'not_demo_host' || ($blocked['code'] ?? '') === 'disabled', 'refusal code is fail-closed');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/services/AuthorizationService.php',
    'config/app.php',
    'config/module-addons.php',
];
foreach ($frozen as $rel) {
    macd_assert(is_file($root . '/' . $rel), 'frozen present ' . $rel);
}

echo "\nModule addon demo preview tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
