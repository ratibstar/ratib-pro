<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Support\GatewayTrustConfig;
use Rateb\PlatformCatalog\Application\Support\InternalActorContext;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;

catalog_test('PlatformIdentityResolver rejects spoofed X-Platform-User-Id without gateway token', static function (): void {
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED=0');
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN'], $_SESSION['platform_user_id']);

    $rbacRepo = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return ['catalog.workflow.publish'];
        }

        public function userIsActive(int $userId): bool
        {
            return true;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return 1;
        }
    };
    $rbac = new RbacService($rbacRepo);

    $_SERVER['HTTP_X_PLATFORM_USER_ID'] = SystemActorContext::SYSTEM_USER_UUID;

    $resolver = buildPlatformIdentityResolver($rbac, $rbacRepo);
    catalog_assert_same(null, $resolver->resolveActorId());

    unset($_SERVER['HTTP_X_PLATFORM_USER_ID']);
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED');
});

catalog_test('PlatformIdentityResolver accepts trusted gateway headers when enabled', static function (): void {
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED=1');
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET=integration-test-secret');
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SESSION['platform_user_id']);

    $rbacRepo = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return ['catalog.workflow.publish'];
        }

        public function userIsActive(int $userId): bool
        {
            return true;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return $uuid === '00000000-0000-4000-8000-000000000001' ? 1 : null;
        }
    };
    $rbac = new RbacService($rbacRepo);

    $_SERVER['HTTP_X_PLATFORM_USER_ID'] = SystemActorContext::SYSTEM_USER_UUID;
    $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN'] = 'integration-test-secret';

    $guard = buildSessionRbacPolicyGuard($rbac, $rbacRepo);
    catalog_assert_true($guard->allows('catalog.workflow.publish'));

    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN']);
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED');
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET');
});

catalog_test('PlatformIdentityResolver resolves session actor without gateway headers', static function (): void {
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED=0');
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN'], $_SESSION['platform_user_id']);

    $rbacRepo = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return $userId === 1 ? ['catalog.workflow.publish'] : [];
        }

        public function userIsActive(int $userId): bool
        {
            return $userId === 1;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }
    };
    $rbac = new RbacService($rbacRepo);

    $_SESSION['platform_user_id'] = 1;

    $guard = buildSessionRbacPolicyGuard($rbac, $rbacRepo);
    catalog_assert_true($guard->allows('catalog.workflow.publish'));

    unset($_SESSION['platform_user_id']);
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED');
});

catalog_test('InternalActorContext provides trusted in-process actor identity', static function (): void {
    catalog_assert_same(null, InternalActorContext::actorId());

    $seen = null;
    InternalActorContext::runAs(1, static function () use (&$seen): void {
        $seen = InternalActorContext::actorId();
    });

    catalog_assert_same(1, $seen);
    catalog_assert_same(null, InternalActorContext::actorId());
});

catalog_test('SystemActorContext uses internal actor context not HTTP headers', static function (): void {
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID']);

    $seen = null;
    SystemActorContext::runAsSystem(static function () use (&$seen): void {
        $seen = InternalActorContext::actorId();
    });

    catalog_assert_same(SystemActorContext::SYSTEM_USER_ID, $seen);
    catalog_assert_false(isset($_SERVER['HTTP_X_PLATFORM_USER_ID']));
});
