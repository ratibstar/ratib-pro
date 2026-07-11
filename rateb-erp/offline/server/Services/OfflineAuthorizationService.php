<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\TenantContext;

/**
 * Authorization for offline sync admin operations (process / conflict resolve).
 * Additive — reuses pos.sync.manage when available; API tokens need pos/inventory ability or unrestricted.
 */
final class OfflineAuthorizationService
{
    public function canManageSync(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }

        // Explicit API token abilities take precedence (avoids session DB lookups).
        $modules = TenantContext::apiModules();
        if (is_array($modules)) {
            // Empty abilities = unrestricted token (same convention as ApiModuleMiddleware).
            if ($modules === []) {
                return TenantContext::companyId() !== null && (int) TenantContext::companyId() > 0;
            }

            return in_array('pos', $modules, true)
                || in_array('inventory', $modules, true)
                || in_array('hr', $modules, true)
                || in_array('procurement', $modules, true)
                || in_array('recruitment', $modules, true)
                || in_array('accounting', $modules, true)
                || in_array('crm', $modules, true)
                || in_array('projects', $modules, true);
        }

        // Session path — prefer permission slug; soft-fail if auth DB unavailable.
        if (function_exists('rateb_can')) {
            try {
                if (rateb_can('pos.sync.manage')) {
                    return true;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    public function isAuthenticatedCompany(): bool
    {
        return (int) (TenantContext::companyId() ?? 0) > 0;
    }
}
