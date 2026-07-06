<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\PaymentBalanceResponse;
use Rateb\App\Pos\DTO\V2\Payment\RecordPaymentRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;

final class RecordPaymentUseCase
{
    public function __construct(
        private readonly PosV2CheckoutAccessValidator $access,
        private readonly PaymentValidator $validator,
        private readonly PosV2PaymentPortInterface $payments,
        private readonly PosV2CartPortInterface $cart,
    ) {
    }

    public function execute(
        PosV2RequestContext $context,
        RecordPaymentRequest $request,
    ): PaymentBalanceResponse {
        $this->access->assertCanRecord($context);
        $scope = PosV2CartScope::fromRequestContext($context);
        $cart = $this->cart->load($scope);
        $this->validator->assertCanRecordPayment($context, $cart->itemCount, $request);

        return $this->payments->record($scope, $request);
    }
}
