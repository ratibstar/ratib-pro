<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Permission gate for Help Center visibility (host + audience + optional module plan gate).
 */
final class HelpPermissionGate
{
    public function audienceLevel(): string
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return 'admin';
        }
        if (function_exists('rateb_can') && (rateb_can('users.manage') || rateb_can('roles.manage') || rateb_can('permissions.manage'))) {
            return 'admin';
        }
        // Managers: common managerial permission signals.
        if (function_exists('rateb_can') && (
            rateb_can('reports.view')
            || rateb_can('approvals.manage')
            || rateb_can('hr.manage')
            || rateb_can('accounting.manage')
        )) {
            return 'manager';
        }

        return 'user';
    }

    public function canSeeAudience(string $audience): bool
    {
        $audience = strtolower(trim($audience));
        if ($audience === '' || $audience === 'all' || $audience === 'user') {
            return true;
        }
        $level = $this->audienceLevel();
        if ($audience === 'manager') {
            return in_array($level, ['manager', 'admin'], true);
        }
        if ($audience === 'admin') {
            return $level === 'admin';
        }

        return true;
    }

    /**
     * Host visibility: "platform" = rateb.sa (and other platform oversight hosts) only.
     */
    public function canSeeHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'all') {
            return true;
        }
        if ($host === 'platform') {
            return function_exists('rateb_is_platform_oversight_host')
                && rateb_is_platform_oversight_host();
        }

        return true;
    }

    /**
     * Soft plan gate: hide the card when the company pack clearly lacks this module.
     * Does not require a nav permission slug — help is readable, not an action.
     */
    public function canSeeModule(?string $moduleGate): bool
    {
        if ($moduleGate === null || $moduleGate === '') {
            return true;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (!function_exists('rateb_nav_tenant_company_id_for_gate')) {
            return true;
        }
        $companyId = (int) rateb_nav_tenant_company_id_for_gate();
        if ($companyId < 1) {
            return false;
        }
        if (!class_exists(\Rateb\App\Services\PlanLimitService::class)) {
            return true;
        }
        try {
            return (new \Rateb\App\Services\PlanLimitService())->companyHasModule($companyId, $moduleGate);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Full catalog-row check used by Help Center listings, search, and articles.
     *
     * @param array<string,mixed> $module
     */
    public function canSeeCatalogModule(array $module): bool
    {
        if (!$this->canSeeHost((string) ($module['host'] ?? 'all'))) {
            return false;
        }
        if (!empty($module['requires_super_admin'])) {
            if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
                return false;
            }
        }
        if (!$this->canSeeAudience((string) ($module['audience'] ?? 'all'))) {
            return false;
        }
        $gate = isset($module['module_gate']) ? (string) $module['module_gate'] : '';

        return $this->canSeeModule($gate !== '' ? $gate : null);
    }

    public function canManageContent(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }

        return function_exists('rateb_can') && rateb_can('help.manage');
    }
}
