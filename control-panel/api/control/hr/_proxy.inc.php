<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/hr/_proxy.inc.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/hr/_proxy.inc.php`.
 */
/**
 * Shared bootstrap for control-panel HR proxies (isolated from main /api/hr URLs).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
$_GET['control'] = '1';
$hrRoot = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'hr';
if (!is_dir($hrRoot)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'HR API root not found']);
    exit;
}
