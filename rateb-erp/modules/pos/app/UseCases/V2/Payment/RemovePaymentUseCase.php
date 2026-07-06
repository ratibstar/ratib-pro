<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;
use Rateb\App\Pos\Services\V2\Payment\PosV2PaymentAccessValidator;

final class RemovePaymentUseCase
{
    public function __construct(
        private readonly PosV2PaymentAccessValidator $access,
        private readonly PaymentValidator $validator,
        private readonly PosV2PaymentPortInterface $payments,
    ) {
    }

    public function execute(PosV2RequestContext $context, string $paymentId): CartResponse
    {
        $this->access->assertCanRecord($context);
        $this->validator->assertRegisterReady($context);

        return $this->payments->remove(
            PosV2CartScope::fromRequestContext($context),
            $paymentId,
        );
    }
}
