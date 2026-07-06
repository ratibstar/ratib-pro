<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Discount\Exceptions;

use RuntimeException;

/** Discount cannot be applied to an empty cart (T11). */
final class PosV2DiscountEmptyCartException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode = 'CART_EMPTY',
        string $message = 'Cart must contain at least one line before applying a discount.',
    ) {
        parent::__construct($message);
    }
}
