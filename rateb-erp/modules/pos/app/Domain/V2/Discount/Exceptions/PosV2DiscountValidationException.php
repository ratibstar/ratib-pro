<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Discount\Exceptions;

use RuntimeException;

/** Discount request or business-rule validation failure (T11). */
final class PosV2DiscountValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
