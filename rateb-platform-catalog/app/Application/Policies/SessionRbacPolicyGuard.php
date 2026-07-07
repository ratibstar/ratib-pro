<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;

final class SessionRbacPolicyGuard implements PolicyGuardInterface
{
    public function __construct(
        private readonly PlatformIdentityResolver $identityResolver,
        private readonly RbacService $rbacService
    ) {
    }

    public function allows(string $permissionSlug): bool
    {
        $userId = $this->actorId();
        if ($userId === null) {
            return false;
        }

        return $this->rbacService->userHasPermission($userId, $permissionSlug);
    }

    public function actorId(): ?int
    {
        return $this->identityResolver->resolveActorId();
    }

    public function isAuthenticated(): bool
    {
        return $this->actorId() !== null;
    }

    public function requireAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Unauthorized', 401);
        }
    }
}
