<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Cart;

use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductInactiveException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartProductNotFoundException;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\DTO\V2\Cart\AddLineRequest;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;

final class AddCartLineUseCase
{
    public function __construct(
        private readonly PosV2CartAccessValidator $access,
        private readonly PosV2CatalogProductPortInterface $catalog,
        private readonly PosV2CartPortInterface $cart,
    ) {
    }

    public function execute(PosV2RequestContext $context, AddLineRequest $request): CartResponse
    {
        $this->access->assertCanModify($context);
        $this->assertProductAvailable($context, $request->productId);

        return $this->cart->addLine(
            PosV2CartScope::fromRequestContext($context),
            $request->productId,
            $request->qty,
        );
    }

    private function assertProductAvailable(PosV2RequestContext $context, int $productId): void
    {
        $product = $this->catalog->findById(
            PosV2CatalogScope::fromRequestContext($context),
            $productId,
        );

        if ($product === null) {
            throw new PosV2CartProductNotFoundException($productId);
        }

        if (!$product->inStock) {
            throw new PosV2CartProductInactiveException($productId);
        }
    }
}
