<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class ProductVariantPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function viewList(): void
    {
        if (!$this->guard->allows('catalog.products.view')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function create(): void
    {
        if (!$this->guard->allows('catalog.variants.manage')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
