<?php
declare(strict_types=1);

/**
 * SaaS marketplace catalog + checkout presentation (no payment).
 * Run: php rateb-erp/tests/ModuleAddonMarketplaceUiTest.php
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

function macui_assert(bool $cond, string $label): void
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

function macui_set_flag(?string $value): void
{
    $name = ModuleAddonService::FLAG_NAME;
    if ($value === null || $value === '') {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

$catalog = [
    'crm' => [
        'name' => 'CRM',
        'name_ar' => 'إدارة علاقات العملاء',
        'description' => 'Manage customers from one workspace.',
        'description_ar' => 'أدر العملاء من مساحة عمل واحدة.',
        'icon' => 'crm',
        'monthly' => 49.0,
        'yearly' => 490.0,
        'enabled' => true,
        'featured' => true,
        'promo_label' => 'popular',
        'sort_order' => 10,
        'features' => [
            ['en' => 'Customer management', 'ar' => 'إدارة العملاء'],
            ['en' => 'Leads', 'ar' => 'العملاء المحتملون'],
        ],
    ],
    'hr' => [
        'name' => 'HR',
        'monthly' => 29.0,
        'yearly' => 290.0,
        'enabled' => false,
        'features' => ['Employees'],
    ],
    'pos' => [
        'name' => 'POS',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => true,
    ],
];
$addons = new ModuleAddonService($catalog);

macui_assert($addons->isPurchasable('crm') === true, 'enabled priced CRM is purchasable');
macui_assert($addons->isPurchasable('hr') === false, 'disabled module is not purchasable');
macui_assert($addons->isPurchasable('pos') === false, 'zero price is not purchasable');

$en = $addons->localizedDisplay('crm', 'en');
macui_assert(is_array($en) && $en['name'] === 'CRM', 'English name from catalog');
macui_assert(($en['description'] ?? '') === 'Manage customers from one workspace.', 'English description from catalog');
macui_assert(($en['features'][0] ?? '') === 'Customer management', 'English features from catalog');
macui_assert(($en['promo_label'] ?? '') === 'POPULAR', 'English promo label only from catalog');

$ar = $addons->localizedDisplay('crm', 'ar');
macui_assert(is_array($ar) && $ar['name'] === 'إدارة علاقات العملاء', 'Arabic name from catalog');
macui_assert(($ar['description'] ?? '') === 'أدر العملاء من مساحة عمل واحدة.', 'Arabic description from catalog');
macui_assert(($ar['features'][0] ?? '') === 'إدارة العملاء', 'Arabic features from catalog');
macui_assert(($ar['promo_label'] ?? '') === 'الأكثر طلبًا', 'Arabic promo label from catalog');

$checkout = new ModuleAddonCheckoutService($addons);
$saving = ModuleAddonCheckoutService::annualSaving(49.0, 490.0);
macui_assert(is_array($saving), 'positive yearly saving is calculated');
macui_assert(abs((float) $saving['annualized_monthly'] - 588.0) < 0.001, 'annualized monthly is 12 × 49');
macui_assert(abs((float) $saving['amount'] - 98.0) < 0.001, 'saving amount is 98 SAR');
macui_assert(abs((float) $saving['percent'] - 16.67) < 0.01, 'saving percent is ~16.67');
macui_assert(ModuleAddonCheckoutService::annualSaving(49.0, 588.0) === null, 'zero/negative saving is hidden');
macui_assert(ModuleAddonCheckoutService::annualSaving(49.0, 0) === null, 'missing yearly has no saving');
$crmSaving = $checkout->savingsForSlug('crm');
macui_assert(abs((float) ($crmSaving['amount'] ?? 0) - 98.0) < 0.001, 'savingsForSlug uses catalog prices');

macui_set_flag(null);
macui_assert((new ModuleAddonCheckoutService($addons))->isEnabled() === false, 'feature flag OFF');
macui_set_flag('1');
macui_assert((new ModuleAddonCheckoutService($addons))->isEnabled() === true, 'feature flag ON');
$monthlyQuote = $checkout->quote('crm', 'monthly');
$yearlyQuote = $checkout->quote('crm', 'yearly');
macui_assert(abs((float) ($monthlyQuote['unit_price'] ?? 0) - 49.0) < 0.001, 'monthly quote from catalog');
macui_assert(abs((float) ($yearlyQuote['unit_price'] ?? 0) - 490.0) < 0.001, 'yearly quote from catalog');
macui_assert($checkout->quote('hr', 'monthly') === null, 'disabled module has no quote');
macui_assert($checkout->quote('pos', 'monthly') === null, 'zero-price module has no quote');

$prod = require $root . '/config/module-addons.php';
macui_assert(empty($prod['crm']['enabled']) && (float) ($prod['crm']['monthly'] ?? 0) === 0.0, 'production CRM remains fail-closed');
macui_assert(isset($prod['crm']['features']) && $prod['crm']['features'] !== [], 'production catalog has CRM features without enabling commerce');
macui_assert(empty($prod['crm']['promo_label']), 'production CRM does not invent a promo badge');

$view = (string) file_get_contents($root . '/views/billing/module-checkout.php');
$status = (string) file_get_contents($root . '/views/billing/module-status.php');
$ctrl = (string) file_get_contents($root . '/app/controllers/Company/ModuleAddonCheckoutController.php');
$css = (string) file_get_contents($root . '/public/assets/css/module-addon-checkout.css');
$svg = (string) file_get_contents($root . '/views/billing/partials/addon-svg.php');
$nav = (string) file_get_contents($root . '/views/partials/sidebar-nav.php');

macui_assert(str_contains($view, "\$display['features']"), 'checkout features come from catalog display');
macui_assert(!str_contains($view, 'Customer management'), 'checkout does not hard-code CRM features');
macui_assert(!preg_match('/\b49\b/', $view), 'checkout does not hard-code 49');
macui_assert(str_contains($view, 'Subscribe to') && str_contains($view, 'اشترك في'), 'checkout CTA has EN/AR copy');
macui_assert(str_contains($view, 'addon-svg.php'), 'checkout uses inline SVG partial');
macui_assert(str_contains($view, 'rateb-addon-cycle'), 'monthly/yearly selector is present');
macui_assert(str_contains($view, 'Secure payment via Moyasar'), 'secure payment copy is present');
macui_assert(str_contains($status, 'is enabled for your company'), 'active state copy is present');
macui_assert(str_contains($status, 'openModuleUrl'), 'active state uses existing module URL');
macui_assert(str_contains($status, 'Payment pending') && str_contains($status, 'الدفع قيد الانتظار'), 'status labels include EN/AR');
macui_assert(str_contains($ctrl, 'localizedDisplay'), 'controller reads catalog display, does not invent features');
macui_assert(str_contains($ctrl, 'rateb_app_url'), 'active Open CRM uses existing runtime URL helper');
macui_assert(!str_contains($ctrl, 'PaymentService::initiate'), 'controller still does not call payment directly');
macui_assert(str_contains($css, '--rateb-primary'), 'checkout CSS uses design tokens');
macui_assert(!str_contains($view, '<style'), 'checkout has no inline style block');
macui_assert(str_contains($svg, 'aria-hidden="true"'), 'SVG icons are decorative');
macui_assert(!str_contains($svg, 'http://') && !str_contains($svg, 'https://'), 'no external image URLs in SVG partial');
macui_assert(str_contains($nav, 'isPurchasable($slug)'), 'locked nav still requires platform-controlled isPurchasable');
macui_assert(str_contains($nav, 'rateb_can($permission)'), 'locked nav still requires existing RBAC');
macui_assert(str_contains($nav, 'companyHasModule'), 'locked nav still uses PlanLimitService');
macui_assert(str_contains($nav, 'rateb-nav-locked-hint'), 'locked nav shows purchase hint');

$alias = new ModuleAddonService([
    'crm' => ['name' => 'CRM', 'monthly_price' => 49.0, 'yearly_price' => 490.0, 'enabled' => true],
]);
macui_assert($alias->isPurchasable('crm') === true, 'monthly_price alias is accepted from catalog only');
macui_assert((float) $alias->catalog()['crm']['monthly'] === 49.0, 'normalized catalog exposes monthly');

$urlIcon = new ModuleAddonService([
    'crm' => ['name' => 'CRM', 'monthly' => 49.0, 'enabled' => true, 'icon' => 'https://evil.example/x.svg'],
]);
macui_assert(($urlIcon->catalog()['crm']['icon'] ?? '') === 'default', 'external icon URLs are rejected');

$invented = $addons->localizedDisplay('crm', 'en');
macui_assert(($invented['promo_label'] ?? '') !== 'BEST VALUE', 'BEST VALUE is not invented when catalog says popular');

echo "\nModule addon marketplace UI tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
