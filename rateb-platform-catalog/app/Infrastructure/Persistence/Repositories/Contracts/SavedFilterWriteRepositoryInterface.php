<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface SavedFilterWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed>|null $sort
     */
    public function create(int $userId, string $name, string $entityType, array $filter, ?array $sort, bool $isDefault, bool $isShared): string;

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed>|null $sort
     */
    public function update(string $uuid, int $userId, string $name, array $filter, ?array $sort, bool $isDefault, bool $isShared): bool;

    public function delete(string $uuid, int $userId): bool;
}
