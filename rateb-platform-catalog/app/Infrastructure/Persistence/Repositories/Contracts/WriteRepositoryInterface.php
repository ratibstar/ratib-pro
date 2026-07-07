<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string;

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $uuid, array $data): bool;

    public function softDelete(string $uuid, ?int $actorId = null): bool;
}
