<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class QueueAdminPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function view(): void
    {
        if (!$this->guard->allows('catalog.search.manage')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function manage(): void
    {
        $this->view();
    }
}
