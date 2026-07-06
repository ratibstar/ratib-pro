<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PaymentSummaryDto
{
    /**
     * @param list<PosV2PaymentLineDto> $payments
     */
    public function __construct(
        public array $payments,
        public PosV2MoneyDto $totalDue,
        public PosV2MoneyDto $paid,
        public PosV2MoneyDto $remaining,
        public PosV2MoneyDto $changeDue,
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
            'total_due' => $this->totalDue->toArray(),
            'paid' => $this->paid->toArray(),
            'remaining' => $this->remaining->toArray(),
            'change_due' => $this->changeDue->toArray(),
        ];
    }
}
