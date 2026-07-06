<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Exceptions;

use RuntimeException;

/** Catalog product or barcode lookup miss. */
final class PosV2CatalogNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
