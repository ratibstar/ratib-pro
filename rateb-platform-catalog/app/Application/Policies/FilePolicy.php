<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class FilePolicy
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

    public function upload(): void
    {
        if (!$this->guard->allows('catalog.files.upload')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
