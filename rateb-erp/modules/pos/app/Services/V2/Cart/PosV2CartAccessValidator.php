<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Validates cart access against resolved register permissions (T09). */
final class PosV2CartAccessValidator
{
    public function assertCanModify(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.cart.modify', $permissions, true)) {
            return;
        }

        if (in_array('pos.register.access', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Cart modification is not permitted for this cashier.',
        );
    }

    public function assertCanView(PosV2RequestContext $context): void
    {
        $this->assertCanModify($context);
    }
}
