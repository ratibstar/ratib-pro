<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Catalog;

/** Decimal money value for catalog pricing. */
final readonly class PosV2MoneyDto
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
    }

    /** @return array{amount: string, currency: string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
