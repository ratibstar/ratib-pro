<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\RbacAdminPolicy;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\RbacAdminService;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Application\Validators\RbacAdminValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminWriteRepositoryInterface;

catalog_test('RbacAdminValidator blocks system user role assignment', static function (): void {
    $validator = new RbacAdminValidator(new class implements RbacAdminReadRepositoryInterface {
        public function listRoles(): array
        {
            return [];
        }

        public function findRoleByUuid(string $uuid): ?array
        {
            return ['uuid' => $uuid];
        }

        public function findUserByUuid(string $uuid): ?array
        {
            return ['uuid' => $uuid];
        }

        public function listRolesForUserUuid(string $userUuid): array
        {
            return [];
        }
    });

    try {
        $validator->validateUserRoleAssignment(SystemActorContext::SYSTEM_USER_UUID, []);
        throw new RuntimeException('Expected system user protection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('System user roles cannot be modified', $e->getMessage());
    }
});

catalog_test('RbacAdminService rejects system user role sync', static function (): void {
    $writeState = new class {
        public bool $called = false;
    };

    $guard = new TestPolicyGuard(['catalog.rbac.manage']);
    $service = new RbacAdminService(
        new class implements RbacAdminReadRepositoryInterface {
            public function listRoles(): array
            {
                return [];
            }

            public function findRoleByUuid(string $uuid): ?array
            {
                return null;
            }

            public function findUserByUuid(string $uuid): ?array
            {
                return ['uuid' => $uuid, 'email' => 'system@rateb.local'];
            }

            public function listRolesForUserUuid(string $userUuid): array
            {
                return [['uuid' => 'role-1', 'code' => 'super_admin']];
            }
        },
        new class($writeState) implements RbacAdminWriteRepositoryInterface {
            public function __construct(private object $writeState)
            {
            }

            public function updateRole(string $roleUuid, array $data, ?int $actorId): bool
            {
                return true;
            }

            public function syncUserRoles(string $userUuid, array $roleUuids, ?int $actorId): void
            {
                $this->writeState->called = true;
            }
        },
        new RbacAdminPolicy($guard),
        new RbacAdminValidator(new class implements RbacAdminReadRepositoryInterface {
            public function listRoles(): array
            {
                return [];
            }

            public function findRoleByUuid(string $uuid): ?array
            {
                return null;
            }

            public function findUserByUuid(string $uuid): ?array
            {
                return ['uuid' => $uuid];
            }

            public function listRolesForUserUuid(string $userUuid): array
            {
                return [];
            }
        }),
        new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
            public function append(
                string $entityType,
                string $entityUuid,
                ?int $entityVersion,
                string $action,
                ?int $actorId,
                string $actorType = 'platform_user',
                ?array $before = null,
                ?array $after = null,
                ?string $ipAddress = null
            ): string {
                return 'audit';
            }
        })
    );

    try {
        $service->assignUserRoles(SystemActorContext::SYSTEM_USER_UUID, ['role_uuids' => []]);
        throw new RuntimeException('Expected system user protection');
    } catch (InvalidArgumentException $e) {
        catalog_assert_same('System user roles cannot be modified', $e->getMessage());
    }

    catalog_assert_false($writeState->called);
});
