<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Currency code for register bootstrap. */
final readonly class PosV2CurrencyContext
{
    public function __construct(
        public string $code,
    ) {
    }

    /** @return array{code: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
        ];
    }
}
