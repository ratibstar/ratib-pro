<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Exceptions;

use RuntimeException;

/** Register bootstrap validation failure with stable API error code. */
class PosV2RegisterBootstrapValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
