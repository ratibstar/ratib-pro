<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

final readonly class CartResponse
{
    /**
     * @param list<PosV2CartLineDto> $lines
     */
    public function __construct(
        public array $lines,
        public PosV2CartTotalsDto $totals,
        public int $itemCount,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lines' => array_map(static fn (PosV2CartLineDto $line): array => $line->toArray(), $this->lines),
            'totals' => $this->totals->toArray(),
            'customer' => null,
            'item_count' => $this->itemCount,
        ];
    }
}
