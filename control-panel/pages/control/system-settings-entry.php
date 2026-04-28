<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/system-settings-entry.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/system-settings-entry.php`.
 */
/**
 * System Settings - Redirects to Control Panel Settings.
 * Ratib Program settings (users, visa types) and Control Panel settings are now on one page.
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('control/panel-settings.php') . '?control=1', true, 302);
exit;
