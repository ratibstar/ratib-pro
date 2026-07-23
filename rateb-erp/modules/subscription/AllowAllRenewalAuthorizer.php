<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/** Test / CLI harness authorizer — allow any positive actor id. */
final class AllowAllRenewalAuthorizer implements RenewalAuthorizer
{
    public function canRenew(int $actorId): bool
    {
        return $actorId > 0;
    }
}
