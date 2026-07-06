<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Exceptions;

use RuntimeException;

/** Catalog request validation failure. */
final class PosV2CatalogValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
