<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/settings-api.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/settings-api.php`.
 */
/**
 * Control Panel - System Settings API
 * Uses control panel DB and auth only. No connection to agency/main app.
 */
define('IS_CONTROL_PANEL', true);
define('SETTINGS_API_CONTROL_MODE', true);

// CORS: allow credentials from ratib.sa subdomains (for cross-subdomain API calls)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('/^https?:\/\/([a-z0-9.-]+\.)?ratib\.sa$/i', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

require_once __DIR__ . '/../../includes/config.php';

// Control panel only - require control login
if (empty($_SESSION['control_logged_in'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'permissions' => [], 'message' => 'Control panel login required']);
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS) && !hasControlPermission('view_control_system_settings') && !hasControlPermission('edit_control_system_settings')) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Standalone control panel: main settings-api.php not present.
// Copy api/settings/settings-api.php from Ratib Pro if you need full system settings (users, visa types, etc.).
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => false,
    'message' => 'System settings API not available in standalone control panel. Deploy with Ratib Pro or copy api/settings/ from Ratib Pro.'
], JSON_UNESCAPED_UNICODE);
exit;
