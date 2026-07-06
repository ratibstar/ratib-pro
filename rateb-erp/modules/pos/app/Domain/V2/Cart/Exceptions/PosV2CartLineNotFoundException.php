<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

final class PosV2CartLineNotFoundException extends PosV2CartException
{
    public function __construct(string $lineId)
    {
        parent::__construct(
            sprintf('Cart line %s was not found.', $lineId),
            'LINE_NOT_FOUND',
        );
    }
}
