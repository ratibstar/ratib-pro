<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;

final class RbacAdminValidator
{
    public function __construct(
        private readonly RbacAdminReadRepositoryInterface $readRepository
    ) {
    }

    /**
     * @param list<string> $roleUuids
     */
    public function validateUserRoleAssignment(string $userUuid, array $roleUuids): void
    {
        $this->assertSystemUserProtected($userUuid);

        if ($this->readRepository->findUserByUuid($userUuid) === null) {
            throw new \InvalidArgumentException('User not found');
        }

        if ($roleUuids === []) {
            return;
        }

        foreach ($roleUuids as $roleUuid) {
            if (!is_string($roleUuid) || $roleUuid === '') {
                throw new \InvalidArgumentException('Each role_uuid must be a non-empty string');
            }
            if ($this->readRepository->findRoleByUuid($roleUuid) === null) {
                throw new \InvalidArgumentException('Unknown role: ' . $roleUuid);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function validateRolePatch(array $payload): void
    {
        if (!array_key_exists('name', $payload) && !array_key_exists('status', $payload)) {
            throw new \InvalidArgumentException('At least one of name or status is required');
        }

        if (isset($payload['name']) && trim((string) $payload['name']) === '') {
            throw new \InvalidArgumentException('name cannot be empty');
        }
    }

    public function assertSystemUserProtected(string $userUuid): void
    {
        if ($userUuid === SystemActorContext::SYSTEM_USER_UUID) {
            throw new \InvalidArgumentException('System user roles cannot be modified');
        }
    }
}
