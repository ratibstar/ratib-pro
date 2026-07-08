<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class ChannelPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function viewList(): void
    {
        $this->assert('catalog.channels.manage');
    }

    public function manage(): void
    {
        $this->assert('catalog.channels.manage');
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
