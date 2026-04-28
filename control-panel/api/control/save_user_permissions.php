<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/save_user_permissions.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/save_user_permissions.php`.
 */
/**
 * Control Panel ONLY: Save user permissions to control_admin_permissions.
 * Request path /api/control/ forces control mode so control_conn is always set.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Control panel login required']);
    exit;
}
if (!hasControlPermission('control_admins') && !hasControlPermission('edit_control_admin')) {
    echo json_encode(['success' => false, 'message' => 'Access denied. You do not have permission to manage control admin permissions.']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Control database not available']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['user_id']) || !isset($input['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = (int)$input['user_id'];
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

$permissions = is_array($input['permissions']) ? $input['permissions'] : [];
foreach ($permissions as $perm) {
    if (!is_string($perm)) {
        echo json_encode(['success' => false, 'message' => 'Invalid permission format']);
        exit;
    }
}

try {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_admin_permissions'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Table control_admin_permissions not found. Run config/control_admin_permissions.sql first']);
        exit;
    }
    // Accept user_id from control_admins.id OR users.user_id (control DB may have either/both)
    $userExists = false;
    $chkAdmins = $ctrl->query("SHOW TABLES LIKE 'control_admins'");
    if ($chkAdmins && $chkAdmins->num_rows > 0) {
        $stmt = $ctrl->prepare("SELECT id FROM control_admins WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) $userExists = true;
        $stmt->close();
    }
    if (!$userExists) {
        $chkUsers = $ctrl->query("SHOW TABLES LIKE 'users'");
        if ($chkUsers && $chkUsers->num_rows > 0) {
            $stmt = $ctrl->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $userExists = true;
            $stmt->close();
        }
    }
    if (!$userExists) {
        echo json_encode(['success' => false, 'message' => 'User not found in control_admins or users']);
        exit;
    }

    $permissionsJson = empty($permissions) ? '[]' : json_encode($permissions, JSON_UNESCAPED_UNICODE);
    $stmt = $ctrl->prepare("INSERT INTO control_admin_permissions (user_id, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $ctrl->error]);
        exit;
    }
    $stmt->bind_param("is", $userId, $permissionsJson);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Save failed: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    $savedBack = $permissions;
    try {
        $verify = $ctrl->prepare("SELECT permissions FROM control_admin_permissions WHERE user_id = ? LIMIT 1");
        $verify->bind_param("i", $userId);
        $verify->execute();
        $row = $verify->get_result()->fetch_assoc();
        $verify->close();
        if ($row && isset($row['permissions'])) {
            $dec = json_decode($row['permissions'], true);
            if (is_array($dec)) $savedBack = $dec;
        }
    } catch (Exception $e) { /* keep $savedBack = $permissions */ }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => empty($permissions) ? 'User permissions cleared.' : 'Permissions saved successfully',
    'user_id' => $userId,
    'permissions_count' => count($permissions),
    'source' => 'control',
    'saved_permissions' => $savedBack,
    'db_name' => defined('DB_NAME') ? DB_NAME : null
]);
