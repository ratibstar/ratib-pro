<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;
use Rateb\App\Pos\Services\V2\Payment\PosV2PaymentAccessValidator;

final class PaymentUseCaseFactory
{
    public function createGet(): GetPaymentsUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new GetPaymentsUseCase(
            new PosV2PaymentAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
        );
    }

    public function createCash(): CashPaymentUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new CashPaymentUseCase(
            new PosV2PaymentAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
            $root->repositories->cart,
        );
    }

    public function createRemove(): RemovePaymentUseCase
    {
        $root = PosV2RequestScope::ensure();

        return new RemovePaymentUseCase(
            new PosV2PaymentAccessValidator(),
            new PaymentValidator(),
            $root->repositories->payments,
        );
    }
}
