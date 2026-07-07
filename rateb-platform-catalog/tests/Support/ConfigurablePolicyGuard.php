<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Tests\Support;

use Rateb\PlatformCatalog\Application\Policies\PolicyGuardInterface;

final class ConfigurablePolicyGuard implements PolicyGuardInterface
{
    /** @param callable(string): bool|bool $allowed */
    public function __construct(
        private readonly mixed $allowed = true
    ) {
    }

    public function allows(string $permissionSlug): bool
    {
        if (is_callable($this->allowed)) {
            return (bool) ($this->allowed)($permissionSlug);
        }

        return (bool) $this->allowed;
    }

    public function actorId(): ?int
    {
        return $this->allows('catalog.products.view') ? 1 : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->actorId() !== null;
    }

    public function requireAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Unauthorized', 401);
        }
    }
}
