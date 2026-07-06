<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Payment\Exceptions;

use RuntimeException;

final class PosV2PaymentValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
