<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

interface PolicyGuardInterface
{
    public function allows(string $permissionSlug): bool;

    public function actorId(): ?int;

    public function isAuthenticated(): bool;

    /**
     * @throws \RuntimeException 401 when unauthenticated
     */
    public function requireAuthenticated(): void;
}
