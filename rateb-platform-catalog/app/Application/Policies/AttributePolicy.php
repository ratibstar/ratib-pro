<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class AttributePolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function viewList(): void
    {
        if (!$this->guard->allows('catalog.products.view') && !$this->guard->allows('catalog.attributes.manage')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function viewDetail(): void
    {
        $this->viewList();
    }
}
