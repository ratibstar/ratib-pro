<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Permission gate for Help Center visibility (audience + optional module plan gate).
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

    public function canSeeModule(?string $moduleGate): bool
    {
        if ($moduleGate === null || $moduleGate === '') {
            return true;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (!function_exists('rateb_nav_can')) {
            return true;
        }
        // Soft gate: hide module card when plan/module clearly unavailable.
        try {
            return (bool) rateb_nav_can('', $moduleGate);
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function canManageContent(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }

        return function_exists('rateb_can') && rateb_can('help.manage');
    }
}
