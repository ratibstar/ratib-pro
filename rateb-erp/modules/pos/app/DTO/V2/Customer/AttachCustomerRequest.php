<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Customer;

use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerValidationException;

/** Attach customer to cart request body (T10). */
final readonly class AttachCustomerRequest
{
    public function __construct(
        public int $customerId,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['customer_id'])) {
            throw new PosV2CustomerValidationException(
                'CUSTOMER_ID_REQUIRED',
                'customer_id is required.',
            );
        }

        if (!is_int($payload['customer_id']) && !ctype_digit((string) $payload['customer_id'])) {
            throw new PosV2CustomerValidationException(
                'CUSTOMER_ID_INVALID',
                'customer_id must be a positive integer.',
            );
        }

        $customerId = (int) $payload['customer_id'];
        if ($customerId < 1) {
            throw new PosV2CustomerValidationException(
                'CUSTOMER_ID_INVALID',
                'customer_id must be a positive integer.',
            );
        }

        return new self($customerId);
    }
}
