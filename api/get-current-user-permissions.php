<?php
/**
 * EN: Handles API endpoint/business logic in `api/get-current-user-permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/get-current-user-permissions.php`.
 */
/**
 * Get current logged-in user's permissions
 * Returns JSON array of permission IDs
 */
require_once __DIR__ . '/core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] < 1
    || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'permissions' => [], 'message' => 'Not logged in']);
    exit;
}

try {
    // Single source of truth: same logic as hasPermission() (user-specific JSON, then role, etc.)
    $permissions = getUserPermissions();
    if (!is_array($permissions)) {
        $permissions = [];
    }
    $isAdmin = in_array('*', $permissions, true);
    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'is_admin' => $isAdmin,
        'user_id' => $_SESSION['user_id'],
        'role_id' => $_SESSION['role_id'] ?? null,
        'permission_count' => count($permissions),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    error_log("Error getting user permissions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'permissions' => [],
        'message' => 'Error loading permissions: ' . $e->getMessage()
    ]);
}
?>

