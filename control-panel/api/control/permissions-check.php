<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/permissions-check.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/permissions-check.php`.
 */
/**
 * Control Panel Permissions – full check.
 * GET with ?user_id=N to verify load/save use same DB and table.
 * Returns: control mode, db name, table exists, current row for user_id, and recommendations.
 */
header('Content-Type: application/json; charset=UTF-8');

define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['ok' => false, 'errors' => ['Control panel login required']]);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_ADMINS) && !hasControlPermission('manage_control_roles')) {
    echo json_encode(['ok' => false, 'errors' => ['Access denied']]);
    exit;
}

$report = [
    'ok' => true,
    'checks' => [],
    'errors' => [],
    'db_name' => null,
    'table_exists' => false,
    'user_row' => null,
    'recommendations' => []
];

// 1) Control session
if (empty($_SESSION['control_logged_in'])) {
    $report['ok'] = false;
    $report['errors'][] = 'Not logged in to control panel (control_logged_in missing)';
    $report['checks']['session'] = false;
} else {
    $report['checks']['session'] = true;
}

// 2) control_conn
$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    $report['ok'] = false;
    $report['errors'][] = 'Control database connection (control_conn) not available';
    $report['checks']['control_conn'] = false;
} else {
    $report['checks']['control_conn'] = true;
    $report['db_name'] = defined('DB_NAME') ? DB_NAME : null;
}

// 3) Table control_admin_permissions
if ($ctrl) {
    $chk = @$ctrl->query("SHOW TABLES LIKE 'control_admin_permissions'");
    $report['table_exists'] = $chk && $chk->num_rows > 0;
    if (!$report['table_exists']) {
        $report['ok'] = false;
        $report['errors'][] = 'Table control_admin_permissions does not exist in control DB';
        $report['recommendations'][] = 'Run config/control_admin_permissions.sql (or control_tables_in_main_db.sql) on the control database';
    }
}

// 4) Optional: current row for user_id
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId > 0 && $ctrl && $report['table_exists']) {
    $stmt = $ctrl->prepare("SELECT user_id, permissions, updated_at FROM control_admin_permissions WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $report['user_row'] = [
            'user_id' => (int)$row['user_id'],
            'permissions' => $row['permissions'] !== null ? json_decode($row['permissions'], true) : null,
            'updated_at' => $row['updated_at'] ?? null
        ];
    } else {
        $report['user_row'] = null;
        $report['recommendations'][] = "No row for user_id={$userId} yet; save permissions once to create it.";
    }
}

$report['checks']['table'] = $report['table_exists'];
$report['source'] = 'control';
$report['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
