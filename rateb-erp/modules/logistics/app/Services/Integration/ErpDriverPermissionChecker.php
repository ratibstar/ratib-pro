<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\DriverPermissionChecker;
use Rateb\App\Services\AuthorizationService;

final class ErpDriverPermissionChecker implements DriverPermissionChecker
{
    public function __construct(private AuthorizationService $authz = new AuthorizationService())
    {
    }

    public function canDrive(int $userId, int $companyId): bool
    {
        if ($userId < 1 || $companyId < 1) {
            return false;
        }
        $slugs = $this->authz->userPermissionSlugs($userId);
        if (in_array('logistics.driver', $slugs, true) || in_array('logistics.manage', $slugs, true)) {
            return true;
        }
        // honor permission_implies from config without requiring session-based rateb_can()
        $cfgFile = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        $cfg = is_file($cfgFile) ? require $cfgFile : [];
        $implies = is_array($cfg['permission_implies'] ?? null) ? $cfg['permission_implies'] : [];
        foreach ($implies as $parent => $children) {
            if (!in_array((string) $parent, $slugs, true)) {
                continue;
            }
            foreach ((array) $children as $child) {
                if ((string) $child === 'logistics.driver') {
                    return true;
                }
            }
        }

        return false;
    }
}
