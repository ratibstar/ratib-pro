<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class CompletenessPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function view(): void
    {
        if (!$this->guard->allows('catalog.products.view')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function manage(): void
    {
        if (!$this->guard->allows('catalog.completeness.manage')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
