<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

/**
 * Permissive guard until RBAC session wiring (§4.20) is implemented.
 */
final class PermissivePolicyGuard implements PolicyGuardInterface
{
    public function allows(string $permissionSlug): bool
    {
        unset($permissionSlug);

        return true;
    }

    public function actorId(): ?int
    {
        return 1;
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function requireAuthenticated(): void
    {
    }
}
