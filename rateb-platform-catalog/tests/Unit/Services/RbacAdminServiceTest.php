<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Policies\RbacAdminPolicy;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\RbacAdminService;
use Rateb\PlatformCatalog\Application\Validators\RbacAdminValidator;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminWriteRepositoryInterface;

catalog_test('RbacAdminService lists roles when permitted', static function (): void {
    $guard = new TestPolicyGuard(['catalog.rbac.manage']);
    $service = new RbacAdminService(
        new class implements RbacAdminReadRepositoryInterface {
            public function listRoles(): array
            {
                return [['uuid' => 'role-1', 'code' => 'super_admin', 'name' => 'Super Admin']];
            }

            public function findRoleByUuid(string $uuid): ?array
            {
                return null;
            }

            public function findUserByUuid(string $uuid): ?array
            {
                return null;
            }

            public function listRolesForUserUuid(string $userUuid): array
            {
                return [];
            }
        },
        new class implements RbacAdminWriteRepositoryInterface {
            public function updateRole(string $roleUuid, array $data, ?int $actorId): bool
            {
                return true;
            }

            public function syncUserRoles(string $userUuid, array $roleUuids, ?int $actorId): void
            {
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

    $roles = $service->listRoles();
    catalog_assert_same(1, count($roles));
});

catalog_test('RbacAdminService denies without catalog.rbac.manage', static function (): void {
    $guard = new ConfigurablePolicyGuard(static fn (string $slug): bool => $slug !== 'catalog.rbac.manage');
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
                return null;
            }

            public function listRolesForUserUuid(string $userUuid): array
            {
                return [];
            }
        },
        new class implements RbacAdminWriteRepositoryInterface {
            public function updateRole(string $roleUuid, array $data, ?int $actorId): bool
            {
                return true;
            }

            public function syncUserRoles(string $userUuid, array $roleUuids, ?int $actorId): void
            {
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
                return null;
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
        $service->listRoles();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same(403, (int) $e->getCode());
    }
});
