<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

/**
 * Maps an active RATEB ERP session (rateb_erp) to catalog platform_users.id.
 * No schema changes — super admins map to seeded platform user #1.
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

        if (!empty($_SESSION['rateb_is_super_admin']) && $this->rbacReadRepository->userIsActive(1)) {
            $_SESSION['platform_user_id'] = 1;

            return 1;
        }

        return null;
    }
}
