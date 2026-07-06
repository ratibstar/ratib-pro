<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

use RuntimeException;

class PosV2CartException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
