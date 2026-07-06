<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class PaymentMethodDto
{
    public function __construct(
        public string $code,
        public string $label,
        public string $icon,
    ) {
    }

    /** @return array{code: string, label: string, icon: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'icon' => $this->icon,
        ];
    }
}

final readonly class PaymentSheetResponse
{
    /**
     * @param list<PaymentMethodDto> $allowedMethods
     */
    public function __construct(
        public PosV2CartTotalsDto $totals,
        public array $allowedMethods,
        public PosV2MoneyDto $balanceDue,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'totals' => $this->totals->toArray(),
            'allowed_methods' => array_map(
                static fn (PaymentMethodDto $method): array => $method->toArray(),
                $this->allowedMethods,
            ),
            'balance_due' => $this->balanceDue->toArray(),
        ];
    }
}
