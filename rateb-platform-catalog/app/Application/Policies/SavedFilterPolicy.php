<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class SavedFilterPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function view(): void
    {
        $this->assert('catalog.saved_filters.manage');
    }

    public function manage(): void
    {
        $this->assert('catalog.saved_filters.manage');
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
