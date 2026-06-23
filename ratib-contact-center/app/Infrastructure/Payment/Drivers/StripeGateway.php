<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Payment\Drivers;

use Ratib\ContactCenter\App\Application\Contracts\Payment\PaymentGatewayDriverInterface;
use Ratib\ContactCenter\App\Infrastructure\Payment\PaymentHttpClient;

final class StripeGateway implements PaymentGatewayDriverInterface
{
    public function slug(): string
    {
        return 'stripe';
    }

    public function createCharge(array $credentials, array $charge): array
    {
        $secret = (string) ($credentials['secret_key'] ?? getenv('RCC_STRIPE_SECRET_KEY') ?: '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Stripe secret_key not configured'];
        }
        $amount = (int) round(((float) ($charge['amount'] ?? 0)) * 100);
        $currency = strtolower((string) ($charge['currency'] ?? 'sar'));
        $body = http_build_query([
            'mode' => 'payment',
            'success_url' => (string) ($charge['return_url'] ?? ''),
            'cancel_url' => (string) ($charge['cancel_url'] ?? $charge['return_url'] ?? ''),
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][unit_amount]' => $amount,
            'line_items[0][price_data][product_data][name]' => (string) ($charge['description'] ?? 'RCC Invoice'),
            'line_items[0][quantity]' => 1,
            'client_reference_id' => (string) ($charge['invoice_id'] ?? ''),
            'customer_email' => (string) ($charge['payer_email'] ?? ''),
        ]);
        $res = PaymentHttpClient::request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
            'Authorization' => 'Bearer ' . $secret,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $body);
        $json = $res['json'];
        if ($res['status'] >= 400 || !is_array($json)) {
            return ['ok' => false, 'error' => 'Stripe error HTTP ' . $res['status'], 'raw' => $json ?? []];
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
        $secret = (string) ($credentials['secret_key'] ?? getenv('RCC_STRIPE_SECRET_KEY') ?: '');
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Stripe secret_key not configured'];
        }
        $res = PaymentHttpClient::request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($externalId), [
            'Authorization' => 'Bearer ' . $secret,
        ]);
        $json = $res['json'];
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Invalid Stripe response'];
        }
        $paid = ($json['payment_status'] ?? '') === 'paid';
        return ['ok' => $paid, 'status' => (string) ($json['payment_status'] ?? ''), 'raw' => $json];
    }
}
