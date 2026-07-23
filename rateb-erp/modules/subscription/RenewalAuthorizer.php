<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Authorization port for renewal (billing / platform admin only).
 */
interface RenewalAuthorizer
{
    public function canRenew(int $actorId): bool;
}
