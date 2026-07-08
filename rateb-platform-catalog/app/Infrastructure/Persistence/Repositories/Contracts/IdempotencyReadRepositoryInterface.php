<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface IdempotencyReadRepositoryInterface
{
    public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array;

    public function deleteExpired(): int;
}
