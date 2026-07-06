<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PosV2PaymentLineDto
{
    public function __construct(
        public string $id,
        public PosV2PaymentMethod $method,
        public PosV2MoneyDto $amount,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method->value,
            'amount' => $this->amount->toArray(),
        ];
    }
}
