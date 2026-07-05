<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Locale bundle for register bootstrap. */
final readonly class PosV2LocaleContext
{
    public function __construct(
        public string $code,
        public bool $rtl,
    ) {
    }

    /** @return array{code: string, rtl: bool} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'rtl' => $this->rtl,
        ];
    }
}
