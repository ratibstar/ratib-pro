<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface RbacReadRepositoryInterface
{
    /**
     * @return list<string>
     */
    public function listPermissionSlugsForUser(int $userId): array;

    public function userIsActive(int $userId): bool;
    
    public function findActiveUserIdByUuid(string $uuid): ?int;

    public function findActiveUserIdByEmail(string $email): ?int;
}
