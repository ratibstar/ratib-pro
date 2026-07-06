<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

final class PosV2CartProductNotFoundException extends PosV2CartException
{
    public function __construct(int $productId)
    {
        parent::__construct(
            sprintf('Product %d was not found.', $productId),
            'PRODUCT_NOT_FOUND',
        );
    }
}
