<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/permission_middleware.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/permission_middleware.php`.
 */
/**
 * Permission Middleware - Centralized permission checking for all system components
 * Include this file at the top of any API endpoint or system file to enforce permissions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions.php';

/**
 * Check if user is logged in and has permission for the current action
 * @param string $required_permission The permission required for this action
 * @param bool $return_json Whether to return JSON response (for API endpoints)
 * @return bool|void Returns true if authorized, otherwise exits with error
 */
function checkPermission($required_permission, $return_json = false) {
    // Check if user is logged in (positive user_id = row in `users`)
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 1
        || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        if ($return_json) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
            exit;
        } else {
            header('Location: ' . pageUrl('login.php'));
            exit;
        }
    }
    
    // Check if user has the required permission
    if (!hasPermission($required_permission)) {
        if ($return_json) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Insufficient permissions.', 'code' => 'PERMISSION_DENIED']);
            exit;
        } else {
            // Show unauthorized message for web pages
            checkPermissionOrShowUnauthorized($required_permission);
            return false;
        }
    }
    
    return true;
}

/**
 * Check permission for API endpoints with JSON response
 * @param string $required_permission The permission required
 */
function checkApiPermission($required_permission) {
    checkPermission($required_permission, true);
}

/**
 * Check permission for web pages with redirect/error message
 * @param string $required_permission The permission required
 */
function checkPagePermission($required_permission) {
    return checkPermission($required_permission, false);
}

/**
 * Get current user's permissions for debugging
 * @return array User permissions
 */
function getCurrentUserPermissions() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    return getUserPermissions();
}

/**
 * Check if current user is admin
 * @return bool
 */
function isCurrentUserAdmin() {
    return isAdmin();
}

/**
 * Get current user's role name
 * @return string
 */
function getCurrentUserRole() {
    return getUserRole();
}

/**
 * Log permission check for debugging
 * @param string $permission The permission being checked
 * @param bool $granted Whether permission was granted
 * @param string $file The file where the check occurred
 */
function logPermissionCheck($permission, $granted, $file = '') {
    $log_entry = date('Y-m-d H:i:s') . " - User: " . ($_SESSION['user_id'] ?? 'unknown') . 
                 " - Permission: $permission - Granted: " . ($granted ? 'YES' : 'NO') . 
                 " - File: $file\n";
    
    $log_file = __DIR__ . '/../logs/permission_checks.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Define permission mappings for common actions
$PERMISSION_MAP = [
    // Dashboard permissions
    'dashboard_view' => 'dashboard_view',
    'dashboard_stats' => 'dashboard_view',
    
    // Agent permissions
    'agents_view' => 'agents_view',
    'agents_create' => 'agents_create',
    'agents_edit' => 'agents_edit',
    'agents_delete' => 'agents_delete',
    'agents_activate' => 'agents_edit',
    'agents_deactivate' => 'agents_edit',
    
    // Worker permissions
    'workers_view' => 'workers_view',
    'workers_create' => 'workers_create',
    'workers_edit' => 'workers_edit',
    'workers_delete' => 'workers_delete',
    'workers_activate' => 'workers_edit',
    'workers_deactivate' => 'workers_edit',
    
    // Case permissions
    'cases_view' => 'cases_view',
    'cases_create' => 'cases_create',
    'cases_edit' => 'cases_edit',
    'cases_delete' => 'cases_delete',
    
    // Report permissions
    'reports_view' => 'reports_view',
    'reports_export' => 'reports_export',
    'reports_print' => 'reports_view',
    
    
    
    // System settings permissions
    'settings_view' => 'settings_view',
    'settings_edit' => 'settings_edit',
    'settings_delete' => 'settings_delete',
    
    // User management permissions
    'users_view' => 'users_view',
    'users_create' => 'users_create',
    'users_edit' => 'users_edit',
    'users_delete' => 'users_delete',
    
    // Role management permissions
    'roles_view' => 'roles_view',
    'roles_create' => 'roles_create',
    'roles_edit' => 'roles_edit',
    'roles_delete' => 'roles_delete',
];

/**
 * Get the actual permission name from the permission map
 * @param string $action The action being performed
 * @return string The actual permission name
 */
function getPermissionName($action) {
    global $PERMISSION_MAP;
    return $PERMISSION_MAP[$action] ?? $action;
}

/**
 * Check permission using the permission map
 * @param string $action The action being performed
 * @param bool $return_json Whether to return JSON response
 * @return bool|void
 */
function checkActionPermission($action, $return_json = false) {
    $permission = getPermissionName($action);
    return checkPermission($permission, $return_json);
}

/**
 * Check API action permission with JSON response
 * @param string $action The action being performed
 */
function checkApiActionPermission($action) {
    checkActionPermission($action, true);
}

/**
 * Check page action permission with redirect/error message
 * @param string $action The action being performed
 */
function checkPageActionPermission($action) {
    return checkActionPermission($action, false);
}
?>
