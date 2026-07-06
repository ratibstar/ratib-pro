<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\Cart\Exceptions;

final class PosV2CartProductInactiveException extends PosV2CartException
{
    public function __construct(int $productId)
    {
        parent::__construct(
            sprintf('Product %d is inactive or unavailable.', $productId),
            'PRODUCT_INACTIVE',
        );
    }
}
