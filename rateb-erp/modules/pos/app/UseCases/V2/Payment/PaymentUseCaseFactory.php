<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;

final class PaymentUseCaseFactory
{
    public function createGet(): GetPaymentsUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetPaymentsUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
        );
    }

    public function createCash(): CashPaymentUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new CashPaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
            $root->repositories->cart,
        );
    }

    public function createRemove(): RemovePaymentUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemovePaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
        );
    }

    public function createInitiate(): InitiateChargeUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new InitiateChargeUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->checkout,
            $root->repositories->cart,
        );
    }

    public function createRecord(): RecordPaymentUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RecordPaymentUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
            $root->repositories->cart,
        );
    }

    public function createComplete(): CompleteSaleUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new CompleteSaleUseCase(
            new PosV2CheckoutAccessValidator(),
            new PaymentValidator(),
            $root->repositories->checkout,
        );
    }
}
