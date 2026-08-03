<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;

/**
 * Maps an active RATEB ERP login to catalog platform_users.id.
 * Reads ERP session from the rateb_erp cookie file; stores platform_user_id in rateb_catalog session.
 */
final class ErpSessionIdentityBridge
{
    public function __construct(
        private readonly RbacReadRepositoryInterface $rbacReadRepository,
        private readonly ErpSessionFileReader $erpSessionFileReader
    ) {
    }

    public function resolvePlatformUserId(): ?int
    {
        if (isset($_SESSION['platform_user_id']) && is_numeric($_SESSION['platform_user_id'])) {
            $userId = (int) $_SESSION['platform_user_id'];

            return $this->rbacReadRepository->userIsActive($userId) ? $userId : null;
        }

        $erpSession = $this->resolveErpSessionData();
        return $this->mapErpSessionPayload($erpSession);
    }

    /**
     * @param array<string, mixed> $erpSession
     */
    public function mapErpSessionPayload(array $erpSession): ?int
    {
        $erpUserId = isset($erpSession['rateb_user_id']) ? (int) $erpSession['rateb_user_id'] : 0;
        if ($erpUserId < 1) {
            return null;
        }

        if ($this->isErpPlatformOversightSession($erpSession)) {
            return $this->assignPlatformUser(1);
        }

        $email = isset($erpSession['rateb_user_email']) ? strtolower(trim((string) $erpSession['rateb_user_email'])) : '';
        if ($email !== '') {
            $mapped = $this->rbacReadRepository->findActiveUserIdByEmail($email);
            if ($mapped !== null) {
                return $this->assignPlatformUser($mapped);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveErpSessionData(): array
    {
        if (isset($_SESSION['rateb_user_id']) && (int) $_SESSION['rateb_user_id'] > 0) {
            return $_SESSION;
        }

        return $this->erpSessionFileReader->read();
    }

    /**
     * @param array<string, mixed> $erpSession
     */
    private function isErpPlatformOversightSession(array $erpSession): bool
    {
        if (!$this->isMainPlatformHost()) {
            return false;
        }

        if (!empty($erpSession['rateb_is_super_admin'])) {
            return true;
        }

        return (string) ($erpSession['rateb_portal'] ?? '') === 'admin';
    }

    private function isMainPlatformHost(): bool
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $resolver = dirname(RATEB_CATALOG_ROOT) . '/config/env/erp_agency_resolver.php';
        if (is_file($resolver)) {
            require_once $resolver;
        }

        return function_exists('rateb_erp_is_main_platform_host')
            && rateb_erp_is_main_platform_host($host);
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
