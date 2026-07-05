<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Tax settings from stored POS / company configuration (stored keys only). */
final readonly class PosV2TaxSettingsContext
{
    public function __construct(
        public ?float $rate,
        public ?bool $pricesIncludeTax,
    ) {
    }

    /** @return array<string, bool|float|null> */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'prices_include_tax' => $this->pricesIncludeTax,
        ];
    }
}
