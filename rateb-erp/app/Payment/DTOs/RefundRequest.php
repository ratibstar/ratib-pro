<?php
declare(strict_types=1);

namespace Rateb\App\Payment\DTOs;

final class RefundRequest
{
    public function __construct(
        public readonly string $externalId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $reason = null,
    ) {
    }
}
