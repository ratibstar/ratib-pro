<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Cart;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;

/** Wires cart use cases from the shared composition root (T09). */
final class CartUseCaseFactory
{
    public function createGetCart(): GetCartUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetCartUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->cart,
        );
    }

    public function createAddLine(): AddCartLineUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new AddCartLineUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->catalogProducts,
            $root->repositories->cart,
        );
    }

    public function createUpdateLine(): UpdateCartLineUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new UpdateCartLineUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->cart,
        );
    }

    public function createRemoveLine(): RemoveCartLineUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemoveCartLineUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->cart,
        );
    }

    public function createClear(): ClearCartUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new ClearCartUseCase(
            new PosV2CartAccessValidator(),
            $root->repositories->cart,
        );
    }
}
