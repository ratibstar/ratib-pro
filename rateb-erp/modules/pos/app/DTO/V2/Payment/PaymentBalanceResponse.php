<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PaymentBalanceResponse
{
    /**
     * @param list<PosV2PaymentLineDto> $payments
     */
    public function __construct(
        public array $payments,
        public PosV2MoneyDto $balanceDue,
        public PosV2MoneyDto $changeDue,
        public PosV2MoneyDto $paid,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'payments' => array_map(
                static fn (PosV2PaymentLineDto $line): array => $line->toArray(),
                $this->payments,
            ),
            'balance_due' => $this->balanceDue->toArray(),
            'change_due' => $this->changeDue->toArray(),
            'paid' => $this->paid->toArray(),
        ];
    }
}
