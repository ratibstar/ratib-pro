<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\SessionRbacPolicyGuard;
use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Support\GatewayTrustConfig;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;

function buildSessionRbacPolicyGuard(RbacService $rbac): SessionRbacPolicyGuard
{
    return new SessionRbacPolicyGuard(
        new PlatformIdentityResolver($rbac, new GatewayTrustConfig()),
        $rbac
    );
}
