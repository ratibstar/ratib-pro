<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

/**
 * Maps an active RATEB ERP session (rateb_erp) to catalog platform_users.id.
 * No schema changes — platform oversight maps to seeded super_admin user #1.
 */
final class ErpSessionIdentityBridge
{
    public function __construct(
        private readonly RbacReadRepositoryInterface $rbacReadRepository
    ) {
    }

    public function resolvePlatformUserId(): ?int
    {
        if (isset($_SESSION['platform_user_id']) && is_numeric($_SESSION['platform_user_id'])) {
            $userId = (int) $_SESSION['platform_user_id'];

            return $this->rbacReadRepository->userIsActive($userId) ? $userId : null;
        }

        $erpUserId = isset($_SESSION['rateb_user_id']) ? (int) $_SESSION['rateb_user_id'] : 0;
        if ($erpUserId < 1) {
            return null;
        }

        if ($this->isErpPlatformOversightSession()) {
            return $this->assignPlatformUser(1);
        }

        $email = isset($_SESSION['rateb_user_email']) ? strtolower(trim((string) $_SESSION['rateb_user_email'])) : '';
        if ($email !== '') {
            $mapped = $this->rbacReadRepository->findActiveUserIdByEmail($email);
            if ($mapped !== null) {
                return $this->assignPlatformUser($mapped);
            }
        }

        return null;
    }

    private function isErpPlatformOversightSession(): bool
    {
        if (!empty($_SESSION['rateb_is_super_admin'])) {
            return true;
        }

        return (string) ($_SESSION['rateb_portal'] ?? '') === 'admin';
    }

    private function assignPlatformUser(int $userId): ?int
    {
        if (!$this->rbacReadRepository->userIsActive($userId)) {
            return null;
        }

        $_SESSION['platform_user_id'] = $userId;

        return $userId;
    }
}
