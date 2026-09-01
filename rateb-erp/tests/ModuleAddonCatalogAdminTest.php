<?php
declare(strict_types=1);

/**
 * Platform module catalog admin (availability/pricing) + unpaid add-on invoice void.
 * Run: php rateb-erp/tests/ModuleAddonCatalogAdminTest.php
 */

$root = dirname(__DIR__);
if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\ModuleAddonCheckoutService;
use Rateb\App\Services\ModuleAddonService;

$passed = 0;
$failed = 0;

function maca_assert(bool $cond, string $label): void
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

$catalog = [
    'crm' => [
        'name' => 'CRM',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
        'features' => [['en' => 'Customers', 'ar' => 'العملاء']],
    ],
    'hr' => [
        'name' => 'HR',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
];

$overlayOn = new ModuleAddonService($catalog, null, [
    'crm' => ['enabled' => true, 'monthly' => 49.0, 'yearly' => 490.0],
    'invented' => ['enabled' => true, 'monthly' => 9.0],
]);
maca_assert($overlayOn->isPurchasable('crm') === true, 'DB overlay can enable priced CRM');
maca_assert($overlayOn->isPurchasable('invented') === false, 'unknown overlay slug is ignored');
maca_assert((float) $overlayOn->catalog()['crm']['monthly'] === 49.0, 'overlay monthly is used');
maca_assert(($overlayOn->catalog()['crm']['name'] ?? '') === 'CRM', 'file name remains when overlay omits name');

$zero = new ModuleAddonService($catalog, null, [
    'crm' => ['enabled' => true, 'monthly' => 0, 'yearly' => 0],
]);
maca_assert($zero->isPurchasable('crm') === false, 'enabled with zero price is not purchasable');

$disabled = new ModuleAddonService($catalog, null, [
    'crm' => ['enabled' => false, 'monthly' => 49.0, 'yearly' => 490.0],
]);
maca_assert($disabled->isPurchasable('crm') === false, 'priced but disabled remains hidden');

$svc = new ModuleAddonService($catalog);
$sanitized = $svc->sanitizeCommerceOverrides([
    'crm' => [
        'enabled' => '1',
        'monthly' => '49',
        'yearly' => '490',
        'featured' => '1',
        'sort_order' => '10',
        'promo_label' => 'popular',
        'features' => "Customers | العملاء\nPipeline",
    ],
    'not-a-module' => ['enabled' => '1', 'monthly' => '99'],
    'hr' => ['enabled' => '1', 'monthly' => '-5', 'promo_label' => 'invented-badge'],
], ['crm', 'hr']);
maca_assert(isset($sanitized['crm']) && !isset($sanitized['not-a-module']), 'unknown slugs are dropped');
maca_assert((float) $sanitized['crm']['monthly'] === 49.0, 'posted monthly is sanitized from catalog admin only');
maca_assert(($sanitized['crm']['promo_label'] ?? '') === 'popular', 'whitelist promo is kept');
maca_assert(($sanitized['hr']['promo_label'] ?? '') === '', 'invented promo is rejected');
maca_assert((float) $sanitized['hr']['monthly'] === 0.0, 'negative price is clamped to zero');
maca_assert(count($sanitized['crm']['features']) === 2, 'features textarea is parsed');

$saving = ModuleAddonCheckoutService::annualSaving(49.0, 490.0);
maca_assert(is_array($saving) && abs(((float) $saving['percent']) - 16.67) < 0.01, 'admin screen uses server-side saving');

$prod = require $root . '/config/module-addons.php';
$expected = ['crm', 'pos', 'hr', 'recruitment', 'logistics', 'marketplace', 'manufacturing', 'payroll', 'accounting', 'projects', 'quality', 'bi', 'website'];
maca_assert(array_keys($prod) === $expected, 'catalog has the 13 commercial modules');
foreach ($expected as $slug) {
    maca_assert(empty($prod[$slug]['enabled']) && (float) ($prod[$slug]['monthly'] ?? 0) === 0.0, $slug . ' production remains fail-closed');
    maca_assert(trim((string) ($prod[$slug]['description'] ?? '')) !== '', $slug . ' has English description');
    maca_assert(trim((string) ($prod[$slug]['description_ar'] ?? '')) !== '', $slug . ' has Arabic description');
    maca_assert(!empty($prod[$slug]['features']), $slug . ' has marketing features');
}

$ctrl = (string) file_get_contents($root . '/app/controllers/Admin/ModuleAddonCatalogController.php');
$routes = (string) file_get_contents($root . '/routes/modules/module-addons.php');
$view = (string) file_get_contents($root . '/views/admin/module-addons/index.php');
$chk = (string) file_get_contents($root . '/app/services/ModuleAddonCheckoutService.php');
$nav = (string) file_get_contents($root . '/views/layouts/main.php');
$cp = (string) file_get_contents($root . '/app/controllers/Admin/CompanyPermissionsController.php');

maca_assert(str_contains($ctrl, 'canManagePlatformCatalog'), 'catalog admin requires platform/preview Super Admin');
maca_assert(!str_contains($ctrl, 'PaymentService') && !str_contains($ctrl, 'Moyasar'), 'catalog admin does not start payment');
maca_assert(!str_contains($ctrl, 'activateFromPaidInvoice'), 'catalog admin does not activate modules');
maca_assert(!str_contains($ctrl, 'companyHasModule'), 'catalog admin is not the runtime entitlement screen');
maca_assert(str_contains($routes, 'rateb_admin_mw()'), 'catalog routes are Super Admin only');
maca_assert(str_contains($routes, '/admin/module-addons/void-invoice'), 'void route is registered');
maca_assert(str_contains($view, 'module-addon-catalog.css') && !str_contains($view, '<style'), 'catalog CSS is a dedicated stylesheet');
maca_assert(str_contains($view, 'modules[') && str_contains($view, '[monthly]'), 'admin form posts catalog fields');
maca_assert(str_contains($nav, 'admin/module-addons'), 'platform nav includes catalog');
maca_assert(str_contains($cp, 'company.modules') || str_contains($cp, 'enabledModulesForCompany'), 'company-permissions remains tenant entitlements');
maca_assert(str_contains($chk, "status <> 'cancelled'"), 'status page ignores cancelled add-on invoices');
maca_assert(str_contains($chk, 'voidUnpaidAddonInvoice'), 'unpaid add-on invoices can be voided');
maca_assert(str_contains($chk, '$pay === \'paid\'') || str_contains($chk, '$pay === "paid"'), 'void refuses paid invoices');
maca_assert(!str_contains($ctrl, 'startCheckout'), 'catalog admin does not start checkout');

$svg = (string) file_get_contents($root . '/views/billing/partials/addon-svg.php');
maca_assert(str_contains($svg, "'pos'") && str_contains($svg, "'hr'") && str_contains($svg, "'website'"), 'SVG set covers the commercial modules');
maca_assert(!str_contains($svg, 'http://') && !str_contains($svg, 'https://'), 'SVG icons stay inline');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/Payment/Gateways/MoyasarGateway.php',
];
foreach ($frozen as $rel) {
    maca_assert(is_file($root . '/' . $rel), 'frozen present ' . $rel);
}

echo "\nModule addon catalog admin tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
