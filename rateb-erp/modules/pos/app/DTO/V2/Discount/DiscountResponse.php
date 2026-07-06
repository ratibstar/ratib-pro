<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;

/** Discount mutation result envelope (T11). */
final readonly class DiscountResponse
{
    public function __construct(
        public CartResponse $cart,
        public string $scope,
        public PosV2DiscountType $type,
        public string $value,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'cart' => $this->cart->toArray(),
            'applied' => [
                'scope' => $this->scope,
                'type' => $this->type->value,
                'value' => $this->value,
            ],
        ];
    }
}
