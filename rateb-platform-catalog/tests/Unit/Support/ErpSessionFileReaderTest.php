<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\ErpSessionFileReader;
use Rateb\PlatformCatalog\Application\Support\ErpSessionIdentityBridge;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

/**
 * @param array<string, mixed> $data
 */
function catalog_test_write_erp_session_file(string $dir, string $sessionId, array $data): void
{
    $payload = '';
    foreach ($data as $key => $value) {
        $payload .= $key . '|' . serialize($value) . ';';
    }

    file_put_contents($dir . '/sess_' . $sessionId, $payload);
}

catalog_test('ErpSessionFileReader decodes ERP session file from disk', static function (): void {
    $dir = sys_get_temp_dir() . '/rateb-erp-sess-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    $sessionId = 'testsessionid123';
    catalog_test_write_erp_session_file($dir, $sessionId, [
        'rateb_user_id' => 99,
        'rateb_is_super_admin' => true,
        'rateb_portal' => 'admin',
    ]);

    $_COOKIE['rateb_erp'] = $sessionId;

    $reader = new ErpSessionFileReader([$dir]);
    $data = $reader->read();
    catalog_assert_same(99, (int) ($data['rateb_user_id'] ?? 0));

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

    unset($_SESSION['platform_user_id'], $_SESSION['rateb_user_id'], $_SESSION['rateb_is_super_admin'], $_SESSION['rateb_portal']);
    $bridge = new ErpSessionIdentityBridge($repo, new ErpSessionFileReader([$dir]));
    catalog_assert_same(1, $bridge->resolvePlatformUserId());

    unset($_COOKIE['rateb_erp'], $_SESSION['platform_user_id']);
    @unlink($dir . '/sess_' . $sessionId);
    @rmdir($dir);
});
