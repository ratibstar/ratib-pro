<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;

final readonly class CashPaymentRequest
{
    public function __construct(
        public string $amount,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['amount'])) {
            throw new PosV2PaymentValidationException(
                'AMOUNT_REQUIRED',
                'amount is required.',
            );
        }

        $amount = is_array($payload['amount'])
            ? trim((string) ($payload['amount']['amount'] ?? ''))
            : trim((string) $payload['amount']);

        if ($amount === '' || !is_numeric($amount)) {
            throw new PosV2PaymentValidationException(
                'AMOUNT_INVALID',
                'amount must be a positive number.',
            );
        }

        if ((float) $amount <= 0) {
            throw new PosV2PaymentValidationException(
                'AMOUNT_NEGATIVE',
                'Negative or zero payment amounts are not allowed.',
            );
        }

        return new self($amount);
    }
}
