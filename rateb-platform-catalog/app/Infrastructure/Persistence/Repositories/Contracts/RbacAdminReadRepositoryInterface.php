<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface RbacAdminReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listRoles(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findRoleByUuid(string $uuid): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByUuid(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRolesForUserUuid(string $userUuid): array;
}
