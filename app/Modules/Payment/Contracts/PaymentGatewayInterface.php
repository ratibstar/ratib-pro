<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Contracts/PaymentGatewayInterface.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Contracts/PaymentGatewayInterface.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResponse;
use App\Modules\Payment\DTOs\VerificationResult;
use App\Modules\Payment\DTOs\WebhookEvent;

interface PaymentGatewayInterface
{
    public function createCharge(ChargeRequest $request): ChargeResponse;

    public function verifyCharge(string $externalId): VerificationResult;

    public function parseWebhookPayload(array $payload): ?WebhookEvent;
}
