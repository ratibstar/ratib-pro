<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

final class MoyasarGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'moyasar';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $secret = (string) ($credentials['secret_key'] ?? getenv('RCC_MOYASAR_SECRET_KEY') ?: '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Moyasar secret_key not configured'];
        }
        $amount = (int) round(((float) ($charge['amount'] ?? 0)) * 100);
        $payload = json_encode([
            'amount' => $amount,
            'currency' => strtoupper((string) ($charge['currency'] ?? 'SAR')),
            'description' => (string) ($charge['description'] ?? 'RCC Invoice'),
            'callback_url' => (string) ($charge['return_url'] ?? ''),
            'metadata' => ['invoice_id' => (string) ($charge['invoice_id'] ?? '')],
        ], JSON_UNESCAPED_UNICODE);
        $res = PaymentHttpClient::request('POST', 'https://api.moyasar.com/v1/invoices', [
            'Authorization' => 'Basic ' . base64_encode($secret . ':'),
            'Content-Type' => 'application/json',
        ], $payload);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Moyasar invoice failed HTTP ' . $res['status']];
        }
        return [
            'ok' => true,
            'redirect_url' => (string) ($json['url'] ?? ''),
            'external_id' => (string) ($json['id'] ?? ''),
            'raw' => $json,
        ];
    }

    public function verifyCharge(array $credentials, string $externalId): array
    {
        $secret = (string) ($credentials['secret_key'] ?? getenv('RCC_MOYASAR_SECRET_KEY') ?: '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Moyasar secret_key not configured'];
        }
        $res = PaymentHttpClient::request('GET', 'https://api.moyasar.com/v1/invoices/' . rawurlencode($externalId), [
            'Authorization' => 'Basic ' . base64_encode($secret . ':'),
        ]);
        $json = $res['json'];
        $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
        return ['ok' => $status === 'paid', 'status' => $status, 'raw' => $json ?? []];
    }
}
