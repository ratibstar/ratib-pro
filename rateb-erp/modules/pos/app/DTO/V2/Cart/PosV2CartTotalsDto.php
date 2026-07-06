<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PosV2CartTotalsDto
{
    public function __construct(
        public PosV2MoneyDto $subtotal,
        public PosV2MoneyDto $discount,
        public PosV2MoneyDto $tax,
        public PosV2MoneyDto $total,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal->toArray(),
            'discount' => $this->discount->toArray(),
            'tax' => $this->tax->toArray(),
            'total' => $this->total->toArray(),
        ];
    }
}
