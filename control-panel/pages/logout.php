<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/logout.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/logout.php`.
 */
require_once __DIR__ . '/../includes/config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

header('Location: ' . pageUrl('login.php') . '?message=logged_out');
exit;
