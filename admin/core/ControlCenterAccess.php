<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/ControlCenterAccess.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/ControlCenterAccess.php`.
 */
declare(strict_types=1);

final class ControlCenterAccess
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN = 'ADMIN';
    public const VIEWER = 'VIEWER';

    public static function isEnabled(): bool
    {
        if (!defined('ADMIN_CONTROL_MODE') || ADMIN_CONTROL_MODE !== true) {
            return false;
        }
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            return true;
        }
        if (defined('ADMIN_CONTROL_CENTER_ENABLED') && ADMIN_CONTROL_CENTER_ENABLED === true) {
            return true;
        }
        $env = getenv('ADMIN_CONTROL_CENTER_ENABLED');
        if ($env !== false) {
            $env = strtolower(trim((string) $env));
            if (in_array($env, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }
        return true;
    }

    public static function role(): string
    {
        if (self::isSuperAdminTier()) {
            return self::SUPER_ADMIN;
        }
        if (!empty($_SESSION['control_logged_in'])) {
            $perms = $_SESSION['control_permissions'] ?? [];
            $norm = self::normPerms($perms);
            if (in_array('manage_control_roles', $norm, true) || in_array('control_system_settings', $norm, true)) {
                return self::SUPER_ADMIN;
            }
            if (in_array('view_control_system_settings', $norm, true)) {
                return self::VIEWER;
            }
            return self::ADMIN;
        }
        if (function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()) {
            $rid = (int) ($_SESSION['role_id'] ?? 0);
            $tr = strtolower((string) ($_SESSION['tenant_role'] ?? ''));
            if ($rid === 1 || $tr === 'super_admin') {
                return self::SUPER_ADMIN;
            }
            if ($rid === 2 || $tr === 'country_admin') {
                return self::ADMIN;
            }
            return self::VIEWER;
        }
        return self::VIEWER;
    }

    public static function canAccessControlCenter(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        // Control-panel authenticated session should always be allowed.
        // Fine-grained action authorization remains enforced by role checks.
        if (!empty($_SESSION['control_logged_in'])) {
            return true;
        }
        if (self::authIsSuperAdminSafe()) {
            return true;
        }
        if (function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()) {
            return true;
        }
        return false;
    }

    /**
     * @param string[] $allowed
     */
    public static function requireRole(array $allowed): void
    {
        $current = self::role();
        if (!in_array($current, $allowed, true)) {
            throw new RuntimeException('CONTROL_CENTER_FORBIDDEN');
        }
    }

    private static function isSuperAdminTier(): bool
    {
        if (self::authIsSuperAdminSafe()) {
            return true;
        }
        $hasProgram = function_exists('ratib_program_session_is_valid_user')
            ? ratib_program_session_is_valid_user()
            : (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0);
        if ($hasProgram) {
            $roleId = (int) ($_SESSION['role_id'] ?? 0);
            $roleName = strtolower(trim((string) ($_SESSION['role'] ?? '')));
            $username = strtolower(trim((string) ($_SESSION['username'] ?? '')));
            if ($username === 'admin' || $roleId === 1 || strpos($roleName, 'admin') !== false) {
                return true;
            }
            $userPerms = $_SESSION['user_permissions'] ?? [];
            $norm = self::normPerms($userPerms);
            if (in_array('super_admin', $norm, true)
                || in_array('manage_system_settings', $norm, true)
                || in_array('control_system_settings', $norm, true)
            ) {
                return true;
            }
        }
        if (!empty($_SESSION['control_logged_in'])) {
            $controlUsername = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
            if ($controlUsername === 'admin') {
                return true;
            }
            $perms = $_SESSION['control_permissions'] ?? [];
            $norm = self::normPerms($perms);
            if (in_array('control_system_settings', $norm, true)
                || in_array('manage_control_roles', $norm, true)
            ) {
                return true;
            }
        }
        return false;
    }

    private static function authIsSuperAdminSafe(): bool
    {
        if (!class_exists('Auth')) {
            return false;
        }
        if (!method_exists('Auth', 'isSuperAdmin')) {
            return false;
        }
        try {
            return Auth::isSuperAdmin();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param mixed $perms
     * @return string[]
     */
    private static function normPerms($perms): array
    {
        if (!is_array($perms)) {
            return [];
        }
        return array_values(array_filter(array_map(static function ($p) {
            return strtolower(trim((string) $p));
        }, $perms)));
    }
}
