<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;

final readonly class RecordPaymentRequest
{
    public function __construct(
        public PosV2PaymentMethod $method,
        public string $amount,
        public ?string $reference = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $methodRaw = (string) ($payload['method'] ?? '');
        $method = PosV2PaymentMethod::fromString($methodRaw);
        if ($method === null) {
            throw new PosV2PaymentValidationException(
                'PAYMENT_METHOD_INVALID',
                'method must be cash, card, bank, wallet, or gift_card.',
            );
        }

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

        $reference = isset($payload['reference']) ? trim((string) $payload['reference']) : null;
        if ($reference === '') {
            $reference = null;
        }

        return new self($method, $amount, $reference);
    }
}
