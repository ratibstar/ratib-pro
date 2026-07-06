<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\DTO\V2\Cart\CartResponse;

final readonly class PaymentResponse
{
    public function __construct(
        public PaymentSummaryDto $summary,
        public ?CartResponse $cart = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = ['summary' => $this->summary->toArray()];
        if ($this->cart !== null) {
            $data['cart'] = $this->cart->toArray();
        }

        return $data;
    }
}
