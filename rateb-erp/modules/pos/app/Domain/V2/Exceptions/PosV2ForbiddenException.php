<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Exceptions;

use RuntimeException;

/** V2 bootstrap — authenticated but not permitted. */
final class PosV2ForbiddenException extends RuntimeException
{
}
