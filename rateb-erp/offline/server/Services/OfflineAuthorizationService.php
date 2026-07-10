<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;

/**
 * Authorization for offline sync admin operations (process / conflict resolve).
 * Additive — reuses existing pos.sync.manage when available; API tokens need pos ability or unrestricted.
 */
final class OfflineAuthorizationService
{
    public function canManageSync(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (function_exists('rateb_can') && rateb_can('pos.sync.manage')) {
            return true;
        }

        $modules = TenantContext::apiModules();
        // null/empty abilities = unrestricted token (same convention as ApiModuleMiddleware).
        if ($modules === null || $modules === []) {
            return TenantContext::companyId() !== null && (int) TenantContext::companyId() > 0;
        }

        return in_array('pos', $modules, true);
    }

    public function isAuthenticatedCompany(): bool
    {
        return (int) (TenantContext::companyId() ?? 0) > 0;
    }
}
