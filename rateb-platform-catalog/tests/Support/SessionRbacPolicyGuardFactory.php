<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\SessionRbacPolicyGuard;
use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Support\ErpSessionFileReader;
use Rateb\PlatformCatalog\Application\Support\ErpSessionIdentityBridge;
use Rateb\PlatformCatalog\Application\Support\GatewayTrustConfig;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

function buildErpSessionIdentityBridge(
    RbacReadRepositoryInterface $readRepo,
    ?ErpSessionFileReader $fileReader = null
): ErpSessionIdentityBridge {
    return new ErpSessionIdentityBridge($readRepo, $fileReader ?? new ErpSessionFileReader());
}

function buildPlatformIdentityResolver(RbacService $rbac, RbacReadRepositoryInterface $readRepo): PlatformIdentityResolver
{
    return new PlatformIdentityResolver(
        $rbac,
        new GatewayTrustConfig(),
        buildErpSessionIdentityBridge($readRepo)
    );
}

function buildSessionRbacPolicyGuard(RbacService $rbac, RbacReadRepositoryInterface $readRepo): SessionRbacPolicyGuard
{
    return new SessionRbacPolicyGuard(
        buildPlatformIdentityResolver($rbac, $readRepo),
        $rbac
    );
}
