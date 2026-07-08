<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\ErpSessionIdentityBridge;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

catalog_test('ErpSessionIdentityBridge maps ERP super admin to platform user 1', static function (): void {
    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin']);

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
    };

    $bridge = new ErpSessionIdentityBridge($repo);

    catalog_assert_same(null, $bridge->resolvePlatformUserId());

    $_SESSION['rateb_user_id'] = 42;
    $_SESSION['rateb_is_super_admin'] = true;

    catalog_assert_same(1, $bridge->resolvePlatformUserId());
    catalog_assert_same(1, $_SESSION['platform_user_id']);

    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin']);
});

catalog_test('ErpSessionIdentityBridge ignores ERP session without super admin flag', static function (): void {
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
    };

    $_SESSION['rateb_user_id'] = 42;

    $bridge = new ErpSessionIdentityBridge($repo);
    catalog_assert_same(null, $bridge->resolvePlatformUserId());

    unset($_SESSION['rateb_user_id']);
});
