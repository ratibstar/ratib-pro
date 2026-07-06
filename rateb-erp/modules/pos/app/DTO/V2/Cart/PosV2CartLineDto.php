<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Cart;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PosV2CartLineDto
{
    public function __construct(
        public string $lineId,
        public int $productId,
        public string $name,
        public string $qty,
        public PosV2MoneyDto $unitPrice,
        public PosV2MoneyDto $lineTotal,
        public ?string $note = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line_id' => $this->lineId,
            'product_id' => $this->productId,
            'name' => $this->name,
            'qty' => $this->qty,
            'unit_price' => $this->unitPrice->toArray(),
            'line_total' => $this->lineTotal->toArray(),
            'modifiers' => [],
            'note' => $this->note,
        ];
    }
}
