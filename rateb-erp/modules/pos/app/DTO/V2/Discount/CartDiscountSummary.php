<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Discount;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

/** Aggregated discount snapshot for cart responses and bootstrap (T11). */
final readonly class CartDiscountSummary
{
    public function __construct(
        public PosV2MoneyDto $lineDiscountTotal,
        public PosV2MoneyDto $cartDiscountTotal,
        public PosV2MoneyDto $totalDiscount,
        public ?PosV2CartDiscountDto $cartDiscount,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line_discount_total' => $this->lineDiscountTotal->toArray(),
            'cart_discount_total' => $this->cartDiscountTotal->toArray(),
            'total_discount' => $this->totalDiscount->toArray(),
            'cart_discount' => $this->cartDiscount?->toArray(),
        ];
    }
}
