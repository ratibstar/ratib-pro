<?php
declare(strict_types=1);

namespace Rateb\App\Payment\DTOs;

final class PaymentRequest
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $callbackUrl,
        public readonly string $idempotencyKey,
        public readonly ?string $metadataJson = null,
    ) {
    }
}
