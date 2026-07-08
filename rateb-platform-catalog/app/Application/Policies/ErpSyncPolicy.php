<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class ErpSyncPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function view(): void
    {
        $this->assert('catalog.sync.view');
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
