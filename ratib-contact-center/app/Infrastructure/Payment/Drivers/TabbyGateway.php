<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

final class TabbyGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'tabby';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? getenv('RCC_TABBY_API_KEY') ?: '');
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Tabby api_key not configured'];
        }
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_TABBY_API_BASE') ?: 'https://api.tabby.ai');
        $payload = json_encode([
            'payment' => [
                'amount' => (string) number_format((float) ($charge['amount'] ?? 0), 2, '.', ''),
                'currency' => strtoupper((string) ($charge['currency'] ?? 'SAR')),
                'description' => (string) ($charge['description'] ?? 'RCC Invoice'),
                'buyer' => ['email' => (string) ($charge['payer_email'] ?? '')],
                'order' => ['reference_id' => (string) ($charge['invoice_id'] ?? '')],
            ],
            'lang' => 'ar',
            'merchant_code' => (string) ($credentials['merchant_code'] ?? ''),
            'merchant_urls' => [
                'success' => (string) ($charge['return_url'] ?? ''),
                'cancel' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
                'failure' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE);
        $res = PaymentHttpClient::request('POST', rtrim($base, '/') . '/api/v2/checkout', [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ], $payload);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Tabby checkout failed'];
        }
        return [
            'ok' => true,
            'redirect_url' => (string) ($json['configuration']['available_products']['installments'][0]['web_url'] ?? $json['payment']['id'] ?? ''),
            'external_id' => (string) ($json['payment']['id'] ?? ''),
            'raw' => $json,
        ];
    }

    public function verifyCharge(array $credentials, string $externalId): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? getenv('RCC_TABBY_API_KEY') ?: '');
        $base = (string) ($credentials['api_base'] ?? getenv('RCC_TABBY_API_BASE') ?: 'https://api.tabby.ai');
        $res = PaymentHttpClient::request('GET', rtrim($base, '/') . '/api/v2/payments/' . rawurlencode($externalId), [
            'Authorization' => 'Bearer ' . $apiKey,
        ]);
        $json = $res['json'];
        $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
        return ['ok' => $status === 'AUTHORIZED' || $status === 'CLOSED', 'status' => $status, 'raw' => $json ?? []];
    }
}
