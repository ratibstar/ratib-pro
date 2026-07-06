<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

use InvalidArgumentException;

final readonly class PosV2Quantity
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            throw new InvalidArgumentException('Quantity must be numeric.');
        }

        if ((float) $trimmed <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        if (!preg_match('/^\d+(\.\d{1,3})?$/', $trimmed)) {
            throw new InvalidArgumentException('Quantity format is invalid.');
        }

        $this->value = $trimmed;
    }
}
