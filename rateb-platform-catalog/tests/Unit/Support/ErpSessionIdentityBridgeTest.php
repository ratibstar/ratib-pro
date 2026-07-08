<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\ErpSessionIdentityBridge;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

catalog_test('ErpSessionIdentityBridge maps ERP super admin to platform user 1', static function (): void {
    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin'], $_SESSION['rateb_portal']);

    $repo = new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return [];
        }

        public function userIsActive(int $userId): bool
        {
            return $userId === 1;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return null;
        }
    };

    $bridge = new ErpSessionIdentityBridge($repo);

    catalog_assert_same(null, $bridge->resolvePlatformUserId());

    $_SESSION['rateb_user_id'] = 42;
    $_SESSION['rateb_is_super_admin'] = true;

    catalog_assert_same(1, $bridge->resolvePlatformUserId());
    catalog_assert_same(1, $_SESSION['platform_user_id']);

    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin'], $_SESSION['rateb_portal']);
});

catalog_test('ErpSessionIdentityBridge maps ERP admin portal session to platform user 1', static function (): void {
    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin'], $_SESSION['rateb_portal']);

    $repo = new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return [];
        }

        public function userIsActive(int $userId): bool
        {
            return $userId === 1;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return null;
        }
    };

    $_SESSION['rateb_user_id'] = 7;
    $_SESSION['rateb_portal'] = 'admin';

    $bridge = new ErpSessionIdentityBridge($repo);
    catalog_assert_same(1, $bridge->resolvePlatformUserId());

    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_portal']);
});

catalog_test('ErpSessionIdentityBridge maps ERP email to platform user when provisioned', static function (): void {
    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin'], $_SESSION['rateb_portal'], $_SESSION['rateb_user_email']);

    $repo = new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return [];
        }

        public function userIsActive(int $userId): bool
        {
            return $userId === 9;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return $email === 'ops@rateb.local' ? 9 : null;
        }
    };

    $_SESSION['rateb_user_id'] = 55;
    $_SESSION['rateb_portal'] = 'company';
    $_SESSION['rateb_user_email'] = 'ops@rateb.local';

    $bridge = new ErpSessionIdentityBridge($repo);
    catalog_assert_same(9, $bridge->resolvePlatformUserId());

    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_portal'], $_SESSION['rateb_user_email']);
});

catalog_test('ErpSessionIdentityBridge ignores ERP session without mapping', static function (): void {
    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin']);

    $repo = new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return [];
        }

        public function userIsActive(int $userId): bool
        {
            return true;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return null;
        }
    };

    $_SESSION['rateb_user_id'] = 42;
    $_SESSION['rateb_portal'] = 'company';

    $bridge = new ErpSessionIdentityBridge($repo);
    catalog_assert_same(null, $bridge->resolvePlatformUserId());

    unset($_SESSION['rateb_user_id'], $_SESSION['rateb_portal']);
});
