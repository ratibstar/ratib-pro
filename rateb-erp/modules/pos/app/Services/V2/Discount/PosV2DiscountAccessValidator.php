<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Discount;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Validates discount permissions and feature availability (T11). */
final class PosV2DiscountAccessValidator
{
    public function assertCanApply(PosV2RequestContext $context): void
    {
        if (!$context->register->featureFlags->enabled) {
            throw new PosV2ForbiddenException('POS V2 is not enabled for this register.');
        }

        $permissions = $context->register->permissions;
        if (in_array('pos.discount.apply', $permissions, true)) {
            return;
        }

        if (in_array('pos.discount.manage', $permissions, true)) {
            return;
        }

        if (in_array('pos.*', $permissions, true)) {
            return;
        }

        throw new PosV2ForbiddenException(
            'Discount application is not permitted for this cashier.',
        );
    }
}
