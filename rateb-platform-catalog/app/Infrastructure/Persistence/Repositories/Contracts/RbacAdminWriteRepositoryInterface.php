<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface RbacAdminWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function updateRole(string $roleUuid, array $data, ?int $actorId): bool;

    /**
     * @param list<string> $roleUuids
     */
    public function syncUserRoles(string $userUuid, array $roleUuids, ?int $actorId): void;
}
