<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\DTO\V2\Discount\CartDiscountSummary;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;

final readonly class CartResponse
{
    /**
     * @param list<PosV2CartLineDto> $lines
     */
    public function __construct(
        public array $lines,
        public PosV2CartTotalsDto $totals,
        public int $itemCount,
        public ?PosV2CustomerSummaryDto $customer = null,
        public ?CartDiscountSummary $discounts = null,
        public ?PaymentSummaryDto $payments = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lines' => array_map(static fn (PosV2CartLineDto $line): array => $line->toArray(), $this->lines),
            'totals' => $this->totals->toArray(),
            'customer' => $this->customer?->toArray(),
            'discounts' => $this->discounts?->toArray(),
            'payments' => $this->payments?->toArray(),
            'item_count' => $this->itemCount,
        ];
    }
}
