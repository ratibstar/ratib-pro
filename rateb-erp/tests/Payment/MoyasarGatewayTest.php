<?php
declare(strict_types=1);

namespace Rateb\App\Tests\Payment;

use Rateb\App\Payment\Gateways\MoyasarErrorMapper;
use Rateb\App\Payment\Gateways\MoyasarGateway;
use Rateb\App\Payment\DTOs\PaymentRequest;
use Rateb\App\Payment\DTOs\RefundRequest;

/**
 * Moyasar gateway unit tests (no live API calls).
 */
final class MoyasarGatewayTest
{
    public static function bootstrap(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/app/Payment/Contracts/PaymentGatewayInterface.php';
        require_once $root . '/app/Payment/DTOs/PaymentRequest.php';
        require_once $root . '/app/Payment/DTOs/PaymentResponse.php';
        require_once $root . '/app/Payment/DTOs/RefundRequest.php';
        require_once $root . '/app/Payment/DTOs/RefundResponse.php';
        require_once $root . '/app/Payment/DTOs/WebhookEvent.php';
        require_once $root . '/app/Payment/DTOs/PaymentStatus.php';
        require_once $root . '/app/Payment/Gateways/MoyasarErrorMapper.php';
        require_once $root . '/app/Payment/Gateways/MoyasarHttpClient.php';
        require_once $root . '/app/services/Logger.php';
        require_once $root . '/app/Payment/Gateways/MoyasarGateway.php';
    }

    public static function run(): int
    {
        self::bootstrap();
        $fail = 0;
        $check = static function (string $name, bool $ok) use (&$fail): void {
            echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
            if (!$ok) {
                $fail++;
            }
        };

        $gw = new MoyasarGateway(secretKey: '');
        $resp = $gw->createPayment(new PaymentRequest(1, 1, 100.0, 'SAR', 'Test', 'https://example.com/cb', 'idem-1'));
        $check('createPayment fails without secret', !$resp->ok && $resp->errorCode === 'not_configured');

        $refund = $gw->refundPayment(new RefundRequest('pay-1', 50.0, 'SAR'));
        $check('refund fails without secret', !$refund->ok);

        $check('normalize paid', MoyasarErrorMapper::isPaidStatus('paid'));
        $check('normalize captured', MoyasarErrorMapper::isPaidStatus('captured'));
        $check('not paid init', !MoyasarErrorMapper::isPaidStatus('initiated'));

        $event = $gw->verifyWebhook('{"id":"evt-1","type":"payment_paid","data":{"id":"pay-1","status":"paid","amount":10000,"currency":"SAR"}}', []);
        $check('sandbox webhook parses without secret', $event !== null && $event->externalId === 'pay-1');

        $gwWithSecret = new MoyasarGateway(secretKey: 'sk_test', webhookSecret: 'whsec_test');
        $invalid = $gwWithSecret->verifyWebhook('{"id":"x"}', ['X-Moyasar-Signature' => 'bad']);
        $check('webhook rejects bad signature when secret set', $invalid === null);

        $prodNoSecret = new MoyasarGateway(secretKey: 'sk_test', webhookSecret: '', mode: 'production');
        $check('production missing webhook secret rejected', $prodNoSecret->verifyWebhook('{"id":"evt","data":{"id":"pay-1","status":"paid"}}', []) === null);

        $check('supportsRecurring false', !$gw->supportsRecurring());

        return $fail;
    }
}

if (php_sapi_name() === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'MoyasarGatewayTest.php') {
    exit(MoyasarGatewayTest::run() === 0 ? 0 : 1);
}
