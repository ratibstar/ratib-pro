<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class RbacAdminPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function manage(): void
    {
        if (!$this->guard->allows('catalog.rbac.manage')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
