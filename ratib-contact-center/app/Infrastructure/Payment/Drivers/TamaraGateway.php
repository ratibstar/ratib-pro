<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

final class TamaraGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'tamara';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $token = (string) ($credentials['api_token'] ?? getenv('RCC_TAMARA_API_TOKEN') ?: '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'Tamara api_token not configured'];
        }
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_TAMARA_API_BASE') ?: 'https://api-sandbox.tamara.co');
        $amount = number_format((float) ($charge['amount'] ?? 0), 2, '.', '');
        $payload = json_encode([
            'total_amount' => ['amount' => $amount, 'currency' => strtoupper((string) ($charge['currency'] ?? 'SAR'))],
            'description' => (string) ($charge['description'] ?? 'RCC Invoice'),
            'country_code' => 'SA',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'order_reference_id' => (string) ($charge['invoice_id'] ?? ''),
            'consumer' => ['email' => (string) ($charge['payer_email'] ?? '')],
            'merchant_url' => [
                'success' => (string) ($charge['return_url'] ?? ''),
                'failure' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
                'cancel' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE);
        $res = PaymentHttpClient::request('POST', rtrim($base, '/') . '/checkout', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ], $payload);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Tamara checkout failed'];
        }
        return [
            'ok' => true,
            'redirect_url' => (string) ($json['checkout_url'] ?? ''),
            'external_id' => (string) ($json['order_id'] ?? ''),
            'raw' => $json,
        ];
    }

    public function verifyCharge(array $credentials, string $externalId): array
    {
        $token = (string) ($credentials['api_token'] ?? getenv('RCC_TAMARA_API_TOKEN') ?: '');
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_TAMARA_API_BASE') ?: 'https://api-sandbox.tamara.co');
        $res = PaymentHttpClient::request('GET', rtrim($base, '/') . '/orders/' . rawurlencode($externalId), [
            'Authorization' => 'Bearer ' . $token,
        ]);
        $json = $res['json'];
        $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
        return ['ok' => in_array($status, ['approved', 'fully_captured', 'authorised'], true), 'status' => $status, 'raw' => $json ?? []];
    }
}
