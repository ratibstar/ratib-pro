<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Services/TapPaymentService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Services/TapPaymentService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResponse;
use App\Modules\Payment\DTOs\VerificationResult;
use App\Modules\Payment\DTOs\WebhookEvent;

final class TapPaymentService implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.tap.company/v2';

    public function createCharge(ChargeRequest $request): ChargeResponse
    {
        $secretKey = config('payment.tap.secret_key', '');
        if (empty($secretKey)) {
            return ChargeResponse::failed('Tap secret key not configured.');
        }

        $amount = round($request->amount, 2);
        $minAmount = config('payment.tap.min_amount', 0.10);
        $maxAmount = config('payment.tap.max_amount', 100000.00);
        if ($amount < $minAmount || $amount > $maxAmount) {
            return ChargeResponse::failed("Amount must be between {$minAmount} and {$maxAmount}.");
        }

        $nameParts = preg_split('/\s+/', $request->customerName, 2);
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName = $nameParts[1] ?? '';

        $customerData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->customerEmail,
        ];
        if ($request->customerPhone !== null && $request->customerPhone !== '') {
            $customerData['phone'] = [
                'country_code' => '966',
                'number' => preg_replace('/[^0-9]/', '', $request->customerPhone),
            ];
        }

        $metadata = [];
        if ($request->metadataKey !== null && $request->metadataValue !== null) {
            $metadata[$request->metadataKey] = $request->metadataValue;
        }

        $payload = [
            'amount' => $amount,
            'currency' => $request->currencyCode,
            'customer' => $customerData,
            'source' => ['id' => 'src_all'],
            'redirect' => ['url' => $request->successRedirectUrl],
            'metadata' => $metadata,
            'description' => $request->description,
        ];

        $response = $this->httpPost(self::API_BASE . '/charges', $payload, $secretKey);

        if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
            $errorMsg = $this->extractErrorMessage($response['body']);
            return ChargeResponse::failed($errorMsg);
        }

        $data = json_decode($response['body'], true);
        $paymentUrl = $data['transaction']['url'] ?? null;
        $chargeId = $data['id'] ?? null;

        if (empty($paymentUrl) || empty($chargeId)) {
            return ChargeResponse::failed('Invalid response from payment gateway.');
        }

        return ChargeResponse::success($paymentUrl, $chargeId);
    }

    public function verifyCharge(string $externalId): VerificationResult
    {
        $secretKey = config('payment.tap.secret_key', '');
        if (empty($secretKey)) {
            return VerificationResult::failed($externalId, 'Tap secret key not configured.');
        }

        $response = $this->httpGet(self::API_BASE . '/charges/' . $externalId, $secretKey);

        if ($response['http_code'] !== 200) {
            return VerificationResult::failed($externalId, 'Verification request failed.');
        }

        $charge = json_decode($response['body'], true);
        if (! is_array($charge)) {
            return VerificationResult::failed($externalId, 'Invalid response from payment gateway.');
        }

        $status = strtoupper($charge['status'] ?? '');
        if ($status !== 'CAPTURED') {
            return VerificationResult::failed(
                $externalId,
                'Payment was not completed. Status: ' . $status
            );
        }

        $metadata = $charge['metadata'] ?? [];
        $metadataValue = isset($metadata['udf1']) ? trim((string) $metadata['udf1']) : null;

        return VerificationResult::verified(
            $externalId,
            (float) ($charge['amount'] ?? 0),
            $charge['currency'] ?? 'USD',
            isset($charge['customer']['email']) ? trim((string) $charge['customer']['email']) : null,
            $this->buildCustomerName($charge['customer'] ?? []),
            $charge['description'] ?? null,
            $metadataValue,
        );
    }

    public function parseWebhookPayload(array $payload): ?WebhookEvent
    {
        $eventType = $payload['event'] ?? $payload['type'] ?? '';
        if ($eventType === '') {
            return null;
        }

        $object = $payload['object'] ?? $payload;
        $externalId = $object['id'] ?? $payload['id'] ?? '';
        $status = strtoupper($object['status'] ?? $payload['status'] ?? '');
        $amount = isset($object['amount']) ? (float) $object['amount'] : null;
        $currencyCode = $object['currency'] ?? null;
        $description = $object['description'] ?? null;
        $metadata = $object['metadata'] ?? $payload['metadata'] ?? [];
        $metadataValue = isset($metadata['udf1']) ? trim((string) $metadata['udf1']) : null;
        $customer = $object['customer'] ?? [];
        $customerEmail = isset($customer['email']) ? trim((string) $customer['email']) : null;
        $customerName = $this->buildCustomerName($customer);

        return new WebhookEvent(
            eventType: $eventType,
            externalId: $externalId,
            status: $status,
            amount: $amount,
            currencyCode: $currencyCode,
            customerEmail: $customerEmail,
            customerName: $customerName,
            description: $description,
            metadataValue: $metadataValue ?: null,
        );
    }

    private function httpPost(string $url, array $payload, string $secretKey): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $secretKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body ?: '', 'http_code' => $httpCode];
    }

    private function httpGet(string $url, string $secretKey): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body ?: '', 'http_code' => $httpCode];
    }

    private function extractErrorMessage(string $responseBody): string
    {
        $data = json_decode($responseBody, true);
        if (isset($data['errors'][0]['message'])) {
            return $data['errors'][0]['message'];
        }
        if (isset($data['message'])) {
            return $data['message'];
        }
        return 'Payment gateway error.';
    }

    private function buildCustomerName(array $customer): ?string
    {
        $first = trim((string) ($customer['first_name'] ?? ''));
        $last = trim((string) ($customer['last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        return $name !== '' ? $name : null;
    }
}
