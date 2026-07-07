<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Policies;

final class WorkflowPolicy
{
    public function __construct(
        private readonly PolicyGuardInterface $guard
    ) {
    }

    public function submit(): void
    {
        $this->assert('catalog.workflow.submit');
    }

    public function approve(): void
    {
        $this->assert('catalog.workflow.approve');
    }

    public function publish(): void
    {
        $this->assert('catalog.workflow.publish');
    }

    public function comment(): void
    {
        $this->assert('catalog.workflow.comment');
    }

    public function viewHistory(): void
    {
        $this->assert('catalog.products.view');
    }

    public function assertTransitionPermission(?string $permissionSlug): void
    {
        if ($permissionSlug === null || $permissionSlug === '') {
            return;
        }
        $this->assert($permissionSlug);
    }

    private function assert(string $permission): void
    {
        if (!$this->guard->allows($permission)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }
}
