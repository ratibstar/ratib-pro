<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class CategorySchemaPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function view(): void
    {
        $this->assertAny([
            'catalog.category_schemas.manage',
            'catalog.categories.manage',
            'catalog.products.view',
        ]);
    }

    public function manage(): void
    {
        $this->assert('catalog.category_schemas.manage');
    }

    /**
     * @param list<string> $permissions
     */
    private function assertAny(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->guard->allows($permission)) {
                return;
            }
        }

        throw new \RuntimeException('Forbidden', 403);
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
