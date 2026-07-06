<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Checkout;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

final class PosV2CheckoutAccessValidator
{
    public function assertCanInitiate(PosV2RequestContext $context): void
    {
        $this->assertPaymentOrRegister($context);
    }

    public function assertCanComplete(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.sale.complete', $permissions, true)) {
            return;
        }
        if (in_array('pos.register', $permissions, true)) {
            return;
        }
        if (in_array('pos.*', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Sale completion is not permitted for this cashier.',
        );
    }

    public function assertCanRecord(PosV2RequestContext $context): void
    {
        $this->assertPaymentOrRegister($context);
    }

    private function assertPaymentOrRegister(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.payment.record', $permissions, true)) {
            return;
        }
        if (in_array('pos.register', $permissions, true)) {
            return;
        }
        if (in_array('pos.sale.complete', $permissions, true)) {
            return;
        }
        if (in_array('pos.*', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Payment recording is not permitted for this cashier.',
        );
    }
}
