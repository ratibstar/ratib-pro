<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/get-current-user-permissions.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/get-current-user-permissions.php`.
 */
/**
 * Control Panel - Get current user permissions
 * Returns control_permissions from session (set at login). * = full access.
 * Expands parent permissions to include children so frontend data-permission checks work.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!empty($_SESSION['control_logged_in'])) {
    $perms = $_SESSION['control_permissions'] ?? ['*'];
    $isAdmin = ($perms === '*' || (is_array($perms) && in_array('*', $perms)));
    $list = is_array($perms) ? $perms : ['*'];
    if ($list !== ['*'] && !in_array('*', $list, true)) {
        $list = getExpandedControlPermissions($list);
    }
    echo json_encode([
        'success' => true,
        'permissions' => $list,
        'is_admin' => $isAdmin,
        'user_id' => $_SESSION['control_user_id'] ?? 0,
        'role_id' => 1
    ]);
    exit;
}

echo json_encode(['success' => false, 'permissions' => [], 'message' => 'Control panel login required']);
