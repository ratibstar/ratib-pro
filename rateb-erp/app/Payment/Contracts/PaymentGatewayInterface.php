<?php
declare(strict_types=1);

namespace Rateb\App\Payment\Contracts;

use Rateb\App\Payment\DTOs\PaymentRequest;
use Rateb\App\Payment\DTOs\PaymentResponse;
use Rateb\App\Payment\DTOs\PaymentStatus;
use Rateb\App\Payment\DTOs\RefundRequest;
use Rateb\App\Payment\DTOs\RefundResponse;
use Rateb\App\Payment\DTOs\WebhookEvent;

interface PaymentGatewayInterface
{
    public function slug(): string;

    public function createPayment(PaymentRequest $request): PaymentResponse;

    public function capturePayment(string $externalId): PaymentResponse;

    public function refundPayment(RefundRequest $request): RefundResponse;

    public function cancelPayment(string $externalId): PaymentResponse;

    public function getPayment(string $externalId): PaymentStatus;

    /** @param array<string, string> $headers */
    public function verifyWebhook(string $rawBody, array $headers): ?WebhookEvent;

    public function supportsRecurring(): bool;
}
