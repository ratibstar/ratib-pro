<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Customer;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Validates customer access against resolved register permissions (T10). */
final class PosV2CustomerAccessValidator
{
    public function assertCanSearch(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.customer.search', $permissions, true)) {
            return;
        }

        if (in_array('pos.register', $permissions, true)) {
            return;
        }

        if (in_array('pos.register.access', $permissions, true)) {
            return;
        }

        if (in_array('pos.*', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Customer search is not permitted for this cashier.',
        );
    }

    public function assertCanAttach(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.cart.modify', $permissions, true)) {
            return;
        }

        if (in_array('pos.register', $permissions, true)) {
            return;
        }

        if (in_array('pos.register.access', $permissions, true)) {
            return;
        }

        if (in_array('pos.*', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Customer attachment is not permitted for this cashier.',
        );
    }
}
