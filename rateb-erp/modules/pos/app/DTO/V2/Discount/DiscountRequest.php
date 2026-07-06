<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\Exceptions\PosV2DiscountValidationException;
use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;

/** Manual discount apply request (T11). */
final readonly class DiscountRequest
{
    public function __construct(
        public PosV2DiscountType $type,
        public string $value,
        public ?string $lineId = null,
        public ?string $reason = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload, ?string $pathLineId = null): self
    {
        $lineId = $pathLineId ?? (isset($payload['line_id']) ? trim((string) $payload['line_id']) : null);
        if ($lineId === '') {
            $lineId = null;
        }

        $typeRaw = (string) ($payload['type'] ?? '');
        $type = PosV2DiscountType::fromString($typeRaw);
        if ($type === null) {
            throw new PosV2DiscountValidationException(
                'DISCOUNT_TYPE_INVALID',
                'type must be percent or fixed.',
            );
        }

        if (!isset($payload['value'])) {
            throw new PosV2DiscountValidationException(
                'DISCOUNT_VALUE_REQUIRED',
                'value is required.',
            );
        }

        $value = trim((string) $payload['value']);
        if ($value === '' || !is_numeric($value)) {
            throw new PosV2DiscountValidationException(
                'DISCOUNT_VALUE_INVALID',
                'value must be a positive number.',
            );
        }

        if ((float) $value <= 0) {
            throw new PosV2DiscountValidationException(
                'DISCOUNT_VALUE_NEGATIVE',
                'Negative discounts are not allowed.',
            );
        }

        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;
        if ($reason === '') {
            $reason = null;
        }

        return new self($type, $value, $lineId, $reason);
    }
}
