<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** POS discount policy slice from stored settings. */
final readonly class PosV2PosDiscountSettingsContext
{
    public function __construct(
        public ?float $maxCashierPercent,
        public ?float $maxSupervisorPercent,
        public ?float $requireReasonAbovePercent,
        public ?bool $allowLineDiscount,
        public ?bool $allowCartDiscount,
    ) {
    }

    /** @return array<string, bool|float|null> */
    public function toArray(): array
    {
        return [
            'max_cashier_percent' => $this->maxCashierPercent,
            'max_supervisor_percent' => $this->maxSupervisorPercent,
            'require_reason_above_percent' => $this->requireReasonAbovePercent,
            'allow_line_discount' => $this->allowLineDiscount,
            'allow_cart_discount' => $this->allowCartDiscount,
        ];
    }
}
