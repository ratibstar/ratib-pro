<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;

/** Loads current session cart snapshot for register bootstrap (T09). */
final class CartBootstrapProvider
{
    public function __construct(
        private readonly GetCartUseCase $getCart,
    ) {
    }

    public function provide(PosV2RequestContext $context): CartResponse
    {
        return $this->getCart->execute($context);
    }
}
