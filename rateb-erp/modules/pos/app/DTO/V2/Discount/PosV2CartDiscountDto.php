<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Applied cart-level discount snapshot (T11). */
final readonly class PosV2CartDiscountDto
{
    public function __construct(
        public PosV2DiscountType $type,
        public string $value,
        public PosV2MoneyDto $amount,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
            'amount' => $this->amount->toArray(),
        ];
    }
}
