<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/logout.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/logout.php`.
 */
require_once __DIR__ . '/../includes/config.php';
session_unset();
session_destroy();
session_start();
header('Location: ' . pageUrl('login.php') . '?message=logged_out');
exit;
