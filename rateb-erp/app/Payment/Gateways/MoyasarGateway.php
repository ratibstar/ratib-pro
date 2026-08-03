<?php
declare(strict_types=1);

namespace Rateb\App\Payment\Gateways;

use Rateb\App\Payment\Contracts\PaymentGatewayInterface;
use Rateb\App\Payment\DTOs\PaymentRequest;
use Rateb\App\Payment\DTOs\PaymentResponse;
use Rateb\App\Payment\DTOs\PaymentStatus;
use Rateb\App\Payment\DTOs\RefundRequest;
use Rateb\App\Payment\DTOs\RefundResponse;
use Rateb\App\Payment\DTOs\WebhookEvent;
use Rateb\App\Services\Logger;

/** Moyasar driver — API communication only; no ERP business logic. */
final class MoyasarGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly MoyasarHttpClient $http = new MoyasarHttpClient(),
        private readonly string $secretKey = '',
        private readonly string $webhookSecret = '',
        private readonly string $mode = 'sandbox',
    ) {
    }

    public function slug(): string
    {
        return 'moyasar';
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        if ($this->secretKey === '') {
            return PaymentResponse::failure('not_configured', 'Moyasar secret key not configured');
        }

        $amountHalalas = (int) round($request->amount * 100);
        $payload = json_encode([
            'amount' => $amountHalalas,
            'currency' => strtoupper($request->currency),
            'description' => $request->description,
            'callback_url' => $request->callbackUrl,
            'metadata' => [
                'invoice_id' => (string) $request->invoiceId,
                'company_id' => (string) $request->companyId,
                'idempotency_key' => $request->idempotencyKey,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $res = $this->http->request('POST', '/invoices', $this->secretKey, $payload);
        $json = $res['json'];
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($json)) {
            $err = MoyasarErrorMapper::fromResponse($res['status'], $json, 'Moyasar invoice creation failed');

            return PaymentResponse::failure($err['code'], $err['message'], ['http_status' => $res['status'], 'body' => $res['body']]);
        }

        $externalId = (string) ($json['id'] ?? '');
        $redirectUrl = (string) ($json['url'] ?? '');
        if ($externalId === '' || $redirectUrl === '') {
            return PaymentResponse::failure('invalid_response', 'Moyasar returned incomplete invoice data', $json);
        }

        return PaymentResponse::success($externalId, $redirectUrl, $json);
    }

    public function capturePayment(string $externalId): PaymentResponse
    {
        $status = $this->getPayment($externalId);
        if ($status->paid) {
            return PaymentResponse::success($externalId, null, $status->raw);
        }

        return PaymentResponse::failure('not_captured', 'Payment not captured. Status: ' . $status->status, $status->raw);
    }

    public function refundPayment(RefundRequest $request): RefundResponse
    {
        if ($this->secretKey === '') {
            return RefundResponse::failure('not_configured', 'Moyasar secret key not configured');
        }

        $amountHalalas = (int) round($request->amount * 100);
        $payload = json_encode(['amount' => $amountHalalas], JSON_UNESCAPED_UNICODE);
        $path = '/payments/' . rawurlencode($request->externalId) . '/refund';
        $res = $this->http->request('POST', $path, $this->secretKey, $payload);
        $json = $res['json'];
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($json)) {
            $err = MoyasarErrorMapper::fromResponse($res['status'], $json, 'Moyasar refund failed');

            return RefundResponse::failure($err['code'], $err['message'], ['http_status' => $res['status']]);
        }

        return RefundResponse::success((string) ($json['id'] ?? $request->externalId), $json);
    }

    public function cancelPayment(string $externalId): PaymentResponse
    {
        if ($this->secretKey === '') {
            return PaymentResponse::failure('not_configured', 'Moyasar secret key not configured');
        }

        $path = '/invoices/' . rawurlencode($externalId) . '/cancel';
        $res = $this->http->request('POST', $path, $this->secretKey, '{}');
        $json = $res['json'];
        if ($res['status'] < 200 || $res['status'] >= 300) {
            $err = MoyasarErrorMapper::fromResponse($res['status'], $json, 'Moyasar cancel failed');

            return PaymentResponse::failure($err['code'], $err['message']);
        }

        return PaymentResponse::success($externalId, null, is_array($json) ? $json : []);
    }

    public function getPayment(string $externalId): PaymentStatus
    {
        if ($this->secretKey === '') {
            return new PaymentStatus($externalId, 'unknown', false, 0.0, 'SAR', []);
        }

        $invoiceRes = $this->http->request('GET', '/invoices/' . rawurlencode($externalId), $this->secretKey);
        $json = $invoiceRes['json'];
        if (is_array($json) && isset($json['status'])) {
            return $this->mapInvoiceStatus($externalId, $json);
        }

        $payRes = $this->http->request('GET', '/payments/' . rawurlencode($externalId), $this->secretKey);
        $payJson = $payRes['json'];
        if (is_array($payJson) && isset($payJson['status'])) {
            return $this->mapPaymentStatus($externalId, $payJson);
        }

        return new PaymentStatus($externalId, 'unknown', false, 0.0, 'SAR', []);
    }

    /** @param array<string, mixed> $headers */
    public function verifyWebhook(string $rawBody, array $headers): ?WebhookEvent
    {
        if ($this->webhookSecret === '') {
            if ($this->mode === 'production') {
                return null;
            }
            Logger::warning('Moyasar webhook secret not configured; accepting unsigned webhook in sandbox mode');
        } else {
            $provided = $headers['X-Moyasar-Signature']
                ?? $headers['x-moyasar-signature']
                ?? $headers['X-Webhook-Signature']
                ?? $headers['x-webhook-signature']
                ?? '';
            $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
            if ($provided === '' || !hash_equals($expected, $provided)) {
                if (str_starts_with($provided, 'sha256=') && hash_equals($expected, substr($provided, 7))) {
                    // ok
                } else {
                    return null;
                }
            }
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return null;
        }

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $externalId = (string) ($data['id'] ?? $payload['id'] ?? '');
        if ($externalId === '') {
            return null;
        }

        $eventId = (string) ($payload['id'] ?? $data['id'] ?? hash('sha256', $rawBody));
        $eventType = (string) ($payload['type'] ?? $payload['event'] ?? 'payment');
        $status = (string) ($data['status'] ?? $payload['status'] ?? '');
        $amount = isset($data['amount']) ? ((float) $data['amount']) / 100 : null;
        $currency = isset($data['currency']) ? (string) $data['currency'] : null;

        return new WebhookEvent($eventId, $eventType, $externalId, $status, $amount, $currency, $payload);
    }

    public function supportsRecurring(): bool
    {
        return false;
    }

    /** @param array<string, mixed> $json */
    private function mapInvoiceStatus(string $externalId, array $json): PaymentStatus
    {
        $status = MoyasarErrorMapper::normalizeStatus((string) ($json['status'] ?? ''));
        $amount = ((float) ($json['amount'] ?? 0)) / 100;
        $currency = (string) ($json['currency'] ?? 'SAR');

        return new PaymentStatus($externalId, $status, MoyasarErrorMapper::isPaidStatus($status), $amount, $currency, $json);
    }

    /** @param array<string, mixed> $json */
    private function mapPaymentStatus(string $externalId, array $json): PaymentStatus
    {
        $status = MoyasarErrorMapper::normalizeStatus((string) ($json['status'] ?? ''));
        $amount = ((float) ($json['amount'] ?? 0)) / 100;
        $currency = (string) ($json['currency'] ?? 'SAR');

        return new PaymentStatus($externalId, $status, MoyasarErrorMapper::isPaidStatus($status), $amount, $currency, $json);
    }
}
