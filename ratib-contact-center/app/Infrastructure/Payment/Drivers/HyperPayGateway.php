<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

/** HyperPay / OPPWA checkout integration. */
final class HyperPayGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'hyperpay';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $entityId = (string) ($credentials['entity_id'] ?? getenv('RCC_HYPERPAY_ENTITY_ID') ?: '');
        $token = (string) ($credentials['access_token'] ?? getenv('RCC_HYPERPAY_ACCESS_TOKEN') ?: '');
        if ($entityId === '' || $token === '') {
            return ['ok' => false, 'error' => 'HyperPay entity_id/access_token not configured'];
        }
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_HYPERPAY_API_BASE') ?: 'https://eu-test.oppwa.com');
        $amount = number_format((float) ($charge['amount'] ?? 0), 2, '.', '');
        $currency = strtoupper((string) ($charge['currency'] ?? 'SAR'));
        $body = http_build_query([
            'entityId' => $entityId,
            'amount' => $amount,
            'currency' => $currency,
            'paymentType' => 'DB',
            'merchantTransactionId' => (string) ($charge['invoice_id'] ?? uniqid('rcc_', true)),
            'customer.email' => (string) ($charge['payer_email'] ?? ''),
        ]);
        $res = PaymentHttpClient::request('POST', rtrim($base, '/') . '/v1/checkouts', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $body);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'HyperPay checkout failed'];
        }
        $checkoutId = (string) ($json['id'] ?? '');
        $redirect = rtrim($base, '/') . '/v1/paymentWidgets.js?checkoutId=' . rawurlencode($checkoutId);
        return ['ok' => true, 'redirect_url' => $redirect, 'external_id' => $checkoutId, 'raw' => $json];
    }

    public function verifyCharge(array $credentials, string $externalId): array
    {
        $entityId = (string) ($credentials['entity_id'] ?? getenv('RCC_HYPERPAY_ENTITY_ID') ?: '');
        $token = (string) ($credentials['access_token'] ?? getenv('RCC_HYPERPAY_ACCESS_TOKEN') ?: '');
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_HYPERPAY_API_BASE') ?: 'https://eu-test.oppwa.com');
        $res = PaymentHttpClient::request(
            'GET',
            rtrim($base, '/') . '/v1/checkouts/' . rawurlencode($externalId) . '/payment?entityId=' . rawurlencode($entityId),
            ['Authorization' => 'Bearer ' . $token]
        );
        $json = $res['json'];
        $code = is_array($json) ? (string) ($json['result']['code'] ?? '') : '';
        $ok = str_starts_with($code, '000.') || str_starts_with($code, '000.100.1');
        return ['ok' => $ok, 'status' => $code, 'raw' => $json ?? []];
    }
}
