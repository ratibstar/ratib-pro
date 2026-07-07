<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class ChangeRequestPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function list(): void
    {
        $this->assertAny([
            'catalog.change_requests.submit',
            'catalog.change_requests.review',
            'catalog.change_requests.apply',
        ]);
    }

    public function create(): void
    {
        $this->assert('catalog.change_requests.submit');
    }

    public function review(): void
    {
        $this->assert('catalog.change_requests.review');
    }

    public function apply(): void
    {
        $this->assert('catalog.change_requests.apply');
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
