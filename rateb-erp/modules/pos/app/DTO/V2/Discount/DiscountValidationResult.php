<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Discount;

/** Internal discount validation outcome (T11). */
final readonly class DiscountValidationResult
{
    public function __construct(
        public bool $valid,
        public string $errorCode,
        public string $message,
    ) {
    }

    public static function ok(): self
    {
        return new self(true, '', '');
    }

    public static function fail(string $errorCode, string $message): self
    {
        return new self(false, $errorCode, $message);
    }
}
