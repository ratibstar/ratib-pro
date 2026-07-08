<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Support\Request;

final class PlatformIdentityResolver
{
    public function __construct(
        private readonly RbacService $rbacService,
        private readonly GatewayTrustConfig $gatewayTrustConfig,
        private readonly ErpSessionIdentityBridge $erpSessionIdentityBridge
    ) {
    }

    public function resolveActorId(): ?int
    {
        $internal = InternalActorContext::actorId();
        if ($internal !== null) {
            return $internal;
        }

        if (isset($_SESSION['platform_user_id']) && is_numeric($_SESSION['platform_user_id'])) {
            return (int) $_SESSION['platform_user_id'];
        }

        $bridged = $this->erpSessionIdentityBridge->resolvePlatformUserId();
        if ($bridged !== null) {
            return $bridged;
        }

        $headerUser = Request::header($this->gatewayTrustConfig->platformUserIdHeader());
        if ($headerUser === null || $headerUser === '') {
            return null;
        }

        if (!$this->gatewayTrustConfig->isTrustedGatewayRequest()) {
            return null;
        }

        return $this->rbacService->resolveUserId($headerUser);
    }
}
