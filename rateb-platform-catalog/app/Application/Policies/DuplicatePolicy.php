<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class DuplicatePolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function viewList(): void
    {
        $this->assert('catalog.duplicates.view');
    }

    public function viewDetail(): void
    {
        $this->viewList();
    }

    public function scan(): void
    {
        $this->assert('catalog.duplicate_rules.manage');
    }

    public function resolve(): void
    {
        $this->assert('catalog.duplicates.resolve');
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
