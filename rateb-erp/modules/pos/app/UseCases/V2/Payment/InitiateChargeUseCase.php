<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\InitiateChargeRequest;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSheetResponse;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CheckoutPortInterface;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;

final class InitiateChargeUseCase
{
    public function __construct(
        private readonly PosV2CheckoutAccessValidator $access,
        private readonly PaymentValidator $validator,
        private readonly PosV2CheckoutPortInterface $checkout,
        private readonly PosV2CartPortInterface $cart,
    ) {
    }

    public function execute(
        PosV2RequestContext $context,
        InitiateChargeRequest $request,
    ): PaymentSheetResponse {
        $this->access->assertCanInitiate($context);
        $this->validator->assertRegisterReady($context);
        $scope = PosV2CartScope::fromRequestContext($context);
        $cart = $this->cart->load($scope);
        if ($cart->itemCount < 1) {
            throw new \Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException(
                'CART_EMPTY',
                'Cart must contain at least one line before initiating charge.',
            );
        }

        return $this->checkout->initiateCharge($scope, $request);
    }
}
