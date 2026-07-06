<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Customer\Exceptions;

use RuntimeException;

/** Customer record not found (T10). */
final class PosV2CustomerNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $customerId = 0,
    ) {
        parent::__construct($message);
    }
}
