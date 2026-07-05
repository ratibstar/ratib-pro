<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Exceptions;

use RuntimeException;

/** V2 bootstrap — caller is not authenticated. */
final class PosV2UnauthorizedException extends RuntimeException
{
}
