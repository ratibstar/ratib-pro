<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Catalog;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Validates catalog access against resolved register permissions. */
final class PosV2CatalogAccessValidator
{
    public function assertCanView(PosV2RequestContext $context): void
    {
        $permissions = $context->register->permissions;
        if (in_array('pos.catalog.view', $permissions, true)) {
            return;
        }

        if (in_array('pos.register.access', $permissions, true)) {
            return;
        }

        throw new \Rateb\App\Pos\Domain\V2\Exceptions\PosV2ForbiddenException(
            'Catalog access is not permitted for this cashier.',
        );
    }
}
