<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

/**
 * Test-only guard granting all permissions to actor 1.
 */
final class TestPolicyGuard implements PolicyGuardInterface
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
