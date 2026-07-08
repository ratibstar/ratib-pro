<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class CollectionPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function viewList(): void
    {
        $this->assert('catalog.collections.manage');
    }

    public function viewDetail(): void
    {
        $this->viewList();
    }

    public function manage(): void
    {
        $this->assert('catalog.collections.manage');
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
