<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;

/** Validates cash payment business rules (T12). */
final class PaymentValidator
{
    public function __construct(
        private readonly PaymentCalculator $calculator = new PaymentCalculator(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $cartLines
     */
    public function assertCanAddCash(
        PosV2RequestContext $context,
        int $itemCount,
        CashPaymentRequest $request,
    ): void {
        $this->assertRegisterReady($context);

        if ($itemCount < 1) {
            throw new PosV2PaymentValidationException(
                'CART_EMPTY',
                'Cart must contain at least one line before recording payment.',
            );
        }

        try {
            $this->calculator->assertAmountWithinLimit($request->amount);
        } catch (\InvalidArgumentException) {
            throw new PosV2PaymentValidationException(
                'AMOUNT_OVERFLOW',
                'Cash amount exceeds the allowed limit.',
            );
        }
    }

    public function assertRegisterReady(PosV2RequestContext $context): void
    {
        if (!$context->register->registerReady) {
            throw new PosV2PaymentValidationException(
                'REGISTER_NOT_READY',
                'Register is not ready for payments.',
            );
        }

        if (!$context->register->featureFlags->enabled) {
            throw new PosV2PaymentValidationException(
                'POS_V2_DISABLED',
                'POS V2 is not enabled for this register.',
            );
        }
    }
}
