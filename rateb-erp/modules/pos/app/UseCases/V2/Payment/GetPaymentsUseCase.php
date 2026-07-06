<?php

declare(strict_types=1);

namespace Rateb\App\Pos\UseCases\V2\Payment;

use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\PaymentSummaryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PaymentPortInterface;
use Rateb\App\Pos\Services\V2\Payment\PaymentValidator;
use Rateb\App\Pos\Services\V2\Checkout\PosV2CheckoutAccessValidator;

final class GetPaymentsUseCase
{
    public function __construct(
        private readonly PosV2CheckoutAccessValidator $access,
        private readonly PaymentValidator $validator,
        private readonly PosV2PaymentPortInterface $payments,
    ) {
    }

    public function execute(PosV2RequestContext $context): PaymentSummaryDto
    {
        $this->access->assertCanRecord($context);
        $this->validator->assertRegisterReady($context);

        return $this->payments->getSummary(PosV2CartScope::fromRequestContext($context));
    }
}
