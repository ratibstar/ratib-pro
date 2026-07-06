<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

final class PosV2CartInvalidQuantityException extends PosV2CartException
{
    public function __construct(string $message = 'Quantity is invalid.')
    {
        parent::__construct($message, 'INVALID_QUANTITY');
    }
}
