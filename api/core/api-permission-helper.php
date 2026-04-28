<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/api-permission-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/api-permission-helper.php`.
 */
// Must run before any session_start() so Ratib Pro + control SSO see ratib_control session (GET or cookie).
require_once __DIR__ . '/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure Database class with getInstance() is loaded before config.php
// This prevents the old Database class from config/database.php from being loaded first
if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
    if (file_exists(__DIR__ . '/Database.php')) {
        require_once __DIR__ . '/Database.php';
    }
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
$MODULE_PERMISSIONS = require __DIR__ . '/module-permissions.php';

function enforceApiPermission($module, $action) {
    global $MODULE_PERMISSIONS;
    
    // Control panel admin bypass - control panel users are trusted
    if (!empty($_SESSION['control_logged_in'])) {
        return;
    }

    // Check if user is logged in (real `users` row only)
    if (!isset($_SESSION['user_id']) || (int) ($_SESSION['user_id'] ?? 0) < 1
        || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        error_log("enforceApiPermission: User not logged in. Session: " . json_encode([
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'logged_in' => $_SESSION['logged_in'] ?? 'not set'
        ]));
        
        // Instead of exit, throw an exception so the calling code can handle it
        // This prevents the output buffer from being cleared incorrectly
        throw new Exception('Authentication required. Please log in.');
    }
    
    // Admin bypass: If user is admin (role_id = 1), grant all permissions
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        return; // Admin user - grant access
    }
    
    if (!isset($MODULE_PERMISSIONS[$module])) {
        // Module not found - deny access by default for security
        error_log("Permission check failed: Module '{$module}' not found in module-permissions.php");
        throw new Exception('Access denied. Module not configured.');
    }
    
    $map = $MODULE_PERMISSIONS[$module];
    $permission = $map[$action] ?? ($map['*'] ?? null);
    
    if (!$permission) {
        // Action not found - deny access by default for security
        error_log("Permission check failed: Action '{$action}' not found for module '{$module}'");
        throw new Exception('Access denied. Action not configured.');
    }
    
    if (is_array($permission)) {
        foreach ($permission as $perm) {
            if (hasPermission($perm)) {
                return; // User has at least one required permission
            }
        }
        // User doesn't have any of the required permissions
        error_log("Permission check failed: User {$_SESSION['user_id']} does not have any of the required permissions: " . json_encode($permission));
        throw new Exception('Access denied. Missing required permissions.');
    }
    
    // Check single permission
    if (!hasPermission($permission)) {
        error_log("Permission check failed: User {$_SESSION['user_id']} does not have permission '{$permission}' for module '{$module}', action '{$action}'");
        throw new Exception('Access denied. Missing required permission: ' . $permission);
    }
    
    // Permission granted - continue
}

