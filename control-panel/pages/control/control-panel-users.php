<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/control-panel-users.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/control-panel-users.php`.
 */
/**
 * Redirect to panel-users.php (simpler filename)
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('control/panel-users.php') . '?control=1', true, 302);
exit;
