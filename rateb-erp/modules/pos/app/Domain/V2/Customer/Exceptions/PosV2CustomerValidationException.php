<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Customer\Exceptions;

use RuntimeException;

/** Customer request validation failure (T10). */
final class PosV2CustomerValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
