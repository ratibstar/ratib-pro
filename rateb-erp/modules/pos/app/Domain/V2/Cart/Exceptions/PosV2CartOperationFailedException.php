<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

final class PosV2CartOperationFailedException extends PosV2CartException
{
    public function __construct(string $message, string $errorCode = 'CART_OPERATION_FAILED')
    {
        parent::__construct($message, $errorCode);
    }
}
