<?php
declare(strict_types=1);

namespace Rateb\App\Payment\DTOs;

final class PaymentStatus
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
        public readonly bool $paid,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $raw = [],
    ) {
    }

    public function isFailed(): bool
    {
        return in_array(strtolower($this->status), ['failed', 'expired'], true);
    }

    public function isCancelled(): bool
    {
        return in_array(strtolower($this->status), ['cancelled', 'canceled', 'voided'], true);
    }
}
