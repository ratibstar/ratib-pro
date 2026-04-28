<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/index.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/index.php`.
 */
/**
 * Control Panel Entry Point
 */
require_once __DIR__ . '/includes/config.php';
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('control/dashboard.php'));
exit;
