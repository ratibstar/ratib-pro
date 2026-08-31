<?php
declare(strict_types=1);

/**
 * Phase 2 — Module Add-on Commerce checkout (no activation).
 * Run: php rateb-erp/tests/ModuleAddonCheckoutTest.php
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
$skipped = 0;

function mac2_assert(bool $cond, string $label): void
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

function mac2_set_flag(?string $value): void
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

$priced = [
    'crm' => ['name' => 'CRM', 'monthly' => 49.0, 'yearly' => 441.0, 'enabled' => true],
    'pos' => ['name' => 'POS', 'monthly' => 0.0, 'yearly' => 0.0, 'enabled' => true],
    'hr' => ['name' => 'HR', 'monthly' => 29.0, 'yearly' => 0.0, 'enabled' => false],
];
$addons = new ModuleAddonService($priced);
$checkout = new ModuleAddonCheckoutService($addons);

mac2_set_flag(null);
mac2_assert((new ModuleAddonCheckoutService($addons))->isEnabled() === false, 'feature flag OFF by default');
mac2_set_flag('1');
mac2_assert((new ModuleAddonCheckoutService($addons))->isEnabled() === true, 'feature flag ON');

$off = new ModuleAddonCheckoutService($addons);
mac2_set_flag('0');
$offResult = (new ModuleAddonCheckoutService($addons))->startCheckout(1, 'crm', ['cycle' => 'monthly', 'price' => '1']);
mac2_assert(($offResult['ok'] ?? true) === false && ($offResult['code'] ?? '') === 'disabled', 'flag OFF → no checkout');
mac2_assert(!isset($offResult['invoice_id']), 'flag OFF creates no invoice id');

mac2_set_flag('1');
$on = new ModuleAddonCheckoutService($addons);

mac2_assert($on->quote('unknown-slug', 'monthly') === null, 'unknown slug rejected');
mac2_assert($on->quote('pos', 'monthly') === null, 'zero catalog price rejected');
mac2_assert($on->quote('hr', 'monthly') === null, 'known module not enabled for add-on commerce rejected');
mac2_assert($on->quote('crm', 'weekly') === null, 'invalid cycle rejected');

$quote = $on->quote('crm', 'monthly');
mac2_assert(is_array($quote) && abs((float) $quote['unit_price'] - 49.0) < 0.001, 'server catalog monthly price used');
mac2_assert(abs((float) $quote['tax_rate'] - 15.0) < 0.001, 'VAT rate 15%');
mac2_assert(abs((float) $quote['tax_amount'] - 7.35) < 0.001, 'VAT amount 49 * 15% = 7.35');
mac2_assert(abs((float) $quote['total_amount'] - 56.35) < 0.001, 'total 56.35');
mac2_assert((float) $quote['total_amount'] > 0, 'total > 0');
mac2_assert($quote['currency'] === 'SAR', 'currency SAR');

$postedPriceIgnored = $on->quote('crm', 'monthly');
mac2_assert(abs((float) $postedPriceIgnored['unit_price'] - 49.0) < 0.001, 'quote has no HTTP price argument');

$yearly = $on->quote('crm', 'yearly');
mac2_assert(is_array($yearly) && abs((float) $yearly['unit_price'] - 441.0) < 0.001, 'server catalog yearly price used');

$fields = $on->invoiceFieldsFromQuote($quote, 42);
mac2_assert((int) $fields['company_id'] === 42, 'invoice company_id is session company');
mac2_assert($fields['subscription_id'] === null, 'subscription_id is NULL');
mac2_assert($fields['status'] === 'sent', 'invoice status is sent');
mac2_assert($fields['status'] !== 'draft', 'draft invoice is never prepared');
mac2_assert($fields['payment_status'] === 'unpaid', 'invoice payment_status unpaid');
mac2_assert($fields['currency'] === 'SAR', 'invoice currency SAR');
mac2_assert((float) $fields['total_amount'] > 0, 'invoice total > 0');
mac2_assert(abs((float) $fields['tax_amount'] - 7.35) < 0.001, 'invoice VAT matches quote');

$fieldsIgnore = $on->invoiceFieldsFromQuote($quote, 7);
mac2_assert((int) $fieldsIgnore['company_id'] === 7, 'POST company_id cannot appear in invoice fields');

$noCompany = $on->startCheckout(0, 'crm', ['company_id' => '99', 'price' => '1', 'cycle' => 'monthly']);
mac2_assert(($noCompany['code'] ?? '') === 'no_company', 'no session company rejected');

$unknown = $on->startCheckout(1, 'not-a-module', ['cycle' => 'monthly']);
mac2_assert(($unknown['code'] ?? '') === 'unknown_module', 'unknown slug checkout rejected');

$zero = $on->startCheckout(1, 'pos', ['cycle' => 'monthly']);
mac2_assert(($zero['code'] ?? '') === 'not_purchasable', 'non-purchasable slug rejected');

$badCycle = $on->startCheckout(1, 'crm', ['cycle' => 'weekly', 'price' => '999']);
mac2_assert(($badCycle['code'] ?? '') === 'invalid_cycle', 'invalid cycle rejected before payment');

$ctrl = (string) file_get_contents($root . '/app/controllers/Company/ModuleAddonCheckoutController.php');
$svcSrc = (string) file_get_contents($root . '/app/services/ModuleAddonCheckoutService.php');
$routes = (string) file_get_contents($root . '/routes/modules/module-addons.php');
$payRoutes = (string) file_get_contents($root . '/routes/modules/payment.php');

mac2_assert(str_contains($ctrl, "SessionManager::get('rateb_company_id')"), 'company comes from session');
mac2_assert(str_contains($ctrl, 'validateCsrf'), 'CSRF on POST');
mac2_assert(str_contains($ctrl, "\$posted['company_id']"), 'POST company_id ignored');
mac2_assert(str_contains($ctrl, "\$posted['price']"), 'POST price ignored');
mac2_assert(!str_contains($ctrl, 'activateFromPaidInvoice'), 'controller does not activate');
mac2_assert(!str_contains($ctrl, 'updateModules'), 'controller does not write company.modules');
mac2_assert(!str_contains($svcSrc, 'activateFromPaidInvoice'), 'checkout service does not activate');
mac2_assert(!str_contains($svcSrc, 'updateModules'), 'checkout service does not write company.modules');
mac2_assert(str_contains($svcSrc, "initiate(\$invoiceId, 'moyasar', null, \$companyId)"), 'PaymentService called with session company');
mac2_assert(str_contains($svcSrc, "'status' => 'sent'"), 'created invoices are sent');
mac2_assert(str_contains($svcSrc, "(string) \$fields['status'] === 'draft'"), 'draft invoices never sent to PaymentService');
mac2_assert(str_contains($svcSrc, 'payment_init_failed'), 'payment initiation failure is a checkout error, not activation');

mac2_assert(str_contains($routes, 'rateb_erp_mw()'), 'billing routes use empty module middleware');
mac2_assert(!preg_match('/CompanyModuleMiddleware/', $routes), 'billing route file does not attach module entitlement middleware');
mac2_assert(str_contains($routes, "/admin/billing/modules/{slug}"), 'checkout route registered');
mac2_assert(str_contains($routes, "/admin/billing/modules/{slug}/status"), 'status route registered');
mac2_assert(str_contains($payRoutes, 'routes/modules/module-addons.php'), 'module-addons loaded from existing payment route module');

$manifest = (string) file_get_contents($root . '/routes/manifest.php');
mac2_assert(!str_contains($manifest, 'module-addons'), 'manifest.php untouched');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/services/AuthorizationService.php',
    'config/app.php',
    'app/Payment/Gateways/MoyasarGateway.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/controllers/Api/PaymentWebhookController.php',
    'app/services/SaaSAutomationService.php',
    'app/services/AgencyErpMigrationService.php',
    'app/services/CronService.php',
    'bin/erp-cron.php',
    'routes/manifest.php',
    'views/partials/sidebar-nav.php',
    'views/partials/sidebar-hr-nav.php',
];
foreach ($frozen as $rel) {
    mac2_assert(is_file($root . '/' . $rel), 'frozen file still present ' . $rel);
}

mac2_assert(is_file($root . '/views/billing/module-status.php'), 'status view exists');
mac2_assert(is_file($root . '/views/billing/module-checkout.php'), 'checkout view exists');
$statusView = (string) file_get_contents($root . '/views/billing/module-status.php');
mac2_assert(str_contains($statusView, 'Payment received / activation pending'), 'status page has paid-pending-activation copy');
mac2_assert(!str_contains($statusView, 'updateModules'), 'status view does not write modules');

mac2_assert(!str_contains($svcSrc, 'pushModulesToLinkedAgency'), 'no agency sync');
mac2_assert(!str_contains($ctrl, 'CronService'), 'no cron');
mac2_assert(!str_contains($ctrl, 'PaymentWebhook'), 'no webhook hook in controller');

echo "SKIP: ledger/invoice DB integration (local MySQL not required for Phase 2 unit/source tests)\n";
++$skipped;

mac2_set_flag(null);

echo "\nModule addon checkout tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
