<?php
declare(strict_types=1);

/**
 * Payment gateway infrastructure verification tests.
 */
$root = dirname(__DIR__, 2);
$fail = 0;
$check = static function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    if (!$ok) {
        $fail++;
    }
};

$coreFiles = [
    'migrations/220_payment_gateway_infrastructure.sql',
    'app/Payment/Contracts/PaymentGatewayInterface.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/Payment/PaymentTransactionRepository.php',
    'app/Payment/PaymentConfigService.php',
    'app/Payment/PaymentGatewayRegistry.php',
    'app/Payment/PaymentAuditService.php',
    'app/Payment/Gateways/MoyasarGateway.php',
    'app/Payment/Gateways/MoyasarHttpClient.php',
    'app/Payment/Gateways/MoyasarErrorMapper.php',
    'app/controllers/Api/PaymentWebhookController.php',
    'app/controllers/Admin/PaymentGatewaysController.php',
    'app/controllers/Marketing/PortalInvoicePaymentController.php',
    'routes/modules/payment.php',
    'config/payment.php',
    'views/admin/payment-gateways/index.php',
    'views/marketing/portals/payment-success.php',
    'public/assets/js/payment-gateways.js',
    'public/assets/css/payment-gateways.css',
    'public/assets/js/portal-invoice-payment.js',
    'public/assets/css/portal-invoice-payment.css',
];
foreach ($coreFiles as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/220_payment_gateway_infrastructure.sql');
$check('migration gateway settings', str_contains($mig, 'rateb_payment_gateway_settings'));
$check('migration transactions', str_contains($mig, 'rateb_payment_transactions'));
$check('migration webhooks', str_contains($mig, 'rateb_payment_webhooks'));
$check('no alter rateb_invoices', !str_contains($mig, 'ALTER TABLE rateb_invoices'));

$iface = (string) file_get_contents($root . '/app/Payment/Contracts/PaymentGatewayInterface.php');
$check('interface createPayment', str_contains($iface, 'createPayment'));
$check('interface verifyWebhook', str_contains($iface, 'verifyWebhook'));
$check('interface supportsRecurring', str_contains($iface, 'supportsRecurring'));

$svc = (string) file_get_contents($root . '/app/Payment/PaymentService.php');
$check('PaymentService initiate', str_contains($svc, 'function initiate'));
$check('PaymentService finalizeSuccess', str_contains($svc, 'function finalizeSuccess'));
$check('PaymentService calls AccountingService', str_contains($svc, 'AccountingService'));
$check('PaymentService calls BillingAutomationService', str_contains($svc, 'BillingAutomationService'));
$check('PaymentService calls NotificationService', str_contains($svc, 'NotificationService'));
$check('PaymentService FOR UPDATE', str_contains($svc, 'findByIdForUpdate'));
$check('PaymentService no Moyasar import', !str_contains($svc, 'MoyasarGateway'));
$check('PaymentService single Invoice import', substr_count($svc, 'use Rateb\\App\\Models\\Invoice;') === 1);
$check('PaymentService currency mismatch guard', str_contains($svc, 'payment_currency_mismatch'));
$check('PaymentService partial remaining balance', str_contains($svc, 'resolvePayableAmount'));

$moy = (string) file_get_contents($root . '/app/Payment/Gateways/MoyasarGateway.php');
$check('Moyasar SSL verify', str_contains((string) file_get_contents($root . '/app/Payment/Gateways/MoyasarHttpClient.php'), 'CURLOPT_SSL_VERIFYPEER'));
$check('Moyasar timeout', str_contains((string) file_get_contents($root . '/app/Payment/Gateways/MoyasarHttpClient.php'), 'CURLOPT_TIMEOUT'));
$check('Moyasar production fail closed webhook', str_contains($moy, "mode === 'production'") && str_contains($moy, 'webhookSecret === \'\''));
$check('Moyasar no AccountingService', !str_contains($moy, 'AccountingService'));

$wh = (string) file_get_contents($root . '/app/Payment/PaymentWebhookService.php');
$check('webhook invalid signature 401', str_contains($wh, "'http' => 401"));
$check('webhook duplicate 200', str_contains($wh, 'duplicate'));
$check('webhook replay protection', str_contains($wh, 'REPLAY_WINDOW'));
$check('webhook production missing secret rejected', str_contains($wh, 'payment_webhook_rejected_missing_secret'));

$portal = (string) file_get_contents($root . '/app/controllers/Marketing/PortalInvoicePaymentController.php');
$check('portal uses PaymentService', str_contains($portal, 'PaymentService'));
$check('portal no Moyasar', !str_contains($portal, 'Moyasar'));

$finance = (string) file_get_contents($root . '/views/marketing/portals/finance.php');
$check('finance pay online button', str_contains($finance, 'pay_online'));
$check('finance no inline script', !str_contains($finance, '<script'));

$routes = (string) file_get_contents($root . '/routes/modules/payment.php');
$check('webhook route', str_contains($routes, '/api/v1/payments/webhooks/moyasar'));
$check('admin payment gateways route', str_contains($routes, '/admin/payment-gateways'));

$manifest = (string) file_get_contents($root . '/routes/manifest.php');
$check('manifest payment module', str_contains($manifest, "'payment'"));

$gitignore = (string) file_get_contents(dirname($root) . '/.gitignore');
$check('gitignore moyasar secrets', str_contains($gitignore, 'moyasar.secrets.php'));

// Unit-style: MoyasarErrorMapper
require_once $root . '/app/Payment/Gateways/MoyasarErrorMapper.php';
$check('isPaidStatus paid', \Rateb\App\Payment\Gateways\MoyasarErrorMapper::isPaidStatus('paid'));
$check('isPaidStatus failed', !\Rateb\App\Payment\Gateways\MoyasarErrorMapper::isPaidStatus('failed'));

// Mock gateway for PaymentService idempotency structure check
require_once $root . '/app/Payment/DTOs/PaymentStatus.php';
$ps = new \Rateb\App\Payment\DTOs\PaymentStatus('ext-1', 'failed', false, 100.0, 'SAR');
$check('PaymentStatus isFailed', $ps->isFailed());
$cancelled = new \Rateb\App\Payment\DTOs\PaymentStatus('x', 'cancelled', false, 0, 'SAR');
$check('PaymentStatus isCancelled', $cancelled->isCancelled());

echo $fail === 0 ? "ALL PAYMENT TESTS PASSED\n" : "FAILED: $fail\n";

require_once __DIR__ . '/MoyasarGatewayTest.php';
$moyFail = \Rateb\App\Tests\Payment\MoyasarGatewayTest::run();
if ($moyFail > 0) {
    echo "MoyasarGatewayTest failures: $moyFail\n";
    $fail += $moyFail;
}

exit($fail === 0 ? 0 : 1);
