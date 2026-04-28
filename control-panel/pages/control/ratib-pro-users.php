<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/ratib-pro-users.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/ratib-pro-users.php`.
 */
/**
 * Ratib Pro Users - Redirects to Country Users (renamed for clarity).
 * Kept for backward compatibility with old bookmarks.
 */
if (!defined('IS_CONTROL_PANEL')) define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
header('Location: ' . pageUrl('control/country-users.php'));
exit;
