<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\RbacAdminPolicy;
use Rateb\PlatformCatalog\Application\Validators\RbacAdminValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminWriteRepositoryInterface;

final class RbacAdminService
{
    public function __construct(
        private readonly RbacAdminReadRepositoryInterface $readRepository,
        private readonly RbacAdminWriteRepositoryInterface $writeRepository,
        private readonly RbacAdminPolicy $policy,
        private readonly RbacAdminValidator $validator,
        private readonly AuditEventService $auditEventService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRoles(): array
    {
        $this->policy->manage();

        return $this->readRepository->listRoles();
    }

    /**
     * @return array{user: array<string, mixed>, roles: list<array<string, mixed>>}
     */
    public function getUserRoles(string $userUuid): array
    {
        $this->policy->manage();
        $user = $this->readRepository->findUserByUuid($userUuid);
        if ($user === null) {
            throw new \RuntimeException('User not found', 404);
        }

        return [
            'user' => $user,
            'roles' => $this->readRepository->listRolesForUserUuid($userUuid),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{user: array<string, mixed>, roles: list<array<string, mixed>>}
     */
    public function assignUserRoles(string $userUuid, array $payload): array
    {
        $this->policy->manage();
        $roleUuids = is_array($payload['role_uuids'] ?? null) ? $payload['role_uuids'] : [];
        $this->validator->validateUserRoleAssignment($userUuid, $roleUuids);

        $before = $this->readRepository->listRolesForUserUuid($userUuid);
        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;

        $this->writeRepository->syncUserRoles($userUuid, $roleUuids, $actorId);

        $after = $this->readRepository->listRolesForUserUuid($userUuid);
        $user = $this->readRepository->findUserByUuid($userUuid);

        $this->auditEventService->record(
            'platform_user',
            $userUuid,
            'roles_assigned',
            null,
            $actorId,
            ['roles' => $before],
            ['roles' => $after]
        );

        return [
            'user' => (array) $user,
            'roles' => $after,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function patchRole(string $roleUuid, array $payload): array
    {
        $this->policy->manage();
        $this->validator->validateRolePatch($payload);

        $before = $this->readRepository->findRoleByUuid($roleUuid);
        if ($before === null) {
            throw new \RuntimeException('Role not found', 404);
        }

        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;
        $this->writeRepository->updateRole($roleUuid, $payload, $actorId);

        $after = $this->readRepository->findRoleByUuid($roleUuid);

        $this->auditEventService->record(
            'platform_role',
            $roleUuid,
            'update',
            null,
            $actorId,
            $before,
            $after
        );

        return (array) $after;
    }
}
