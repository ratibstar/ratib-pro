<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;

final class GetCartUseCase
{
    public function __construct(
        private readonly PosV2CartAccessValidator $access,
        private readonly PosV2CartPortInterface $cart,
    ) {
    }

    public function execute(PosV2RequestContext $context): CartResponse
    {
        $this->access->assertCanView($context);

        return $this->cart->load(PosV2CartScope::fromRequestContext($context));
    }
}
