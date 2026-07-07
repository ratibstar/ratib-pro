<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class ProductPolicy
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

    public function viewDetail(): void
    {
        $this->viewList();
    }

    public function create(): void
    {
        if (!$this->guard->allows('catalog.products.create')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function update(): void
    {
        if (!$this->guard->allows('catalog.products.edit')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    public function delete(): void
    {
        if (!$this->guard->allows('catalog.products.delete')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
