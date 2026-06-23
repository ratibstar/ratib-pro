<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

final class PayPalGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'paypal';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $token = $this->accessToken($credentials);
        if ($token === '') {
            return ['ok' => false, 'error' => 'PayPal authentication failed'];
        }
        $base = $this->apiBase($credentials);
        $payload = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) ($charge['invoice_id'] ?? ''),
                'amount' => [
                    'currency_code' => strtoupper((string) ($charge['currency'] ?? 'SAR')),
                    'value' => number_format((float) ($charge['amount'] ?? 0), 2, '.', ''),
                ],
                'description' => (string) ($charge['description'] ?? 'RCC Invoice'),
            ]],
            'application_context' => [
                'return_url' => (string) ($charge['return_url'] ?? ''),
                'cancel_url' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE);
        $res = PaymentHttpClient::request('POST', $base . '/v2/checkout/orders', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ], $payload);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'PayPal order creation failed'];
        }
        $approve = '';
        foreach ($json['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approve = (string) ($link['href'] ?? '');
                break;
            }
        }
        return ['ok' => true, 'redirect_url' => $approve, 'external_id' => (string) ($json['id'] ?? ''), 'raw' => $json];
    }

    public function verifyCharge(array $credentials, string $externalId): array
    {
        $token = $this->accessToken($credentials);
        if ($token === '') {
            return ['ok' => false, 'error' => 'PayPal authentication failed'];
        }
        $base = $this->apiBase($credentials);
        $res = PaymentHttpClient::request('POST', $base . '/v2/checkout/orders/' . rawurlencode($externalId) . '/capture', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ], '{}');
        $json = $res['json'];
        $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
        return ['ok' => $status === 'COMPLETED', 'status' => $status, 'raw' => $json ?? []];
    }

    /** @param array<string, mixed> $credentials */
    private function accessToken(array $credentials): string
    {
        $clientId = (string) ($credentials['client_id'] ?? getenv('RCC_PAYPAL_CLIENT_ID') ?: '');
        $secret = (string) ($credentials['client_secret'] ?? getenv('RCC_PAYPAL_CLIENT_SECRET') ?: '');
        if ($clientId === '' || $secret === '') {
            return '';
        }
        $base = $this->apiBase($credentials);
        $res = PaymentHttpClient::request('POST', $base . '/v1/oauth2/token', [
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $secret),
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], 'grant_type=client_credentials');
        return is_array($res['json']) ? (string) ($res['json']['access_token'] ?? '') : '';
    }

    /** @param array<string, mixed> $credentials */
    private function apiBase(array $credentials): string
    {
        $sandbox = !empty($credentials['is_sandbox']) || (getenv('RCC_PAYPAL_SANDBOX') ?: '1') === '1';
        return $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
