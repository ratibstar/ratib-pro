<?php
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

requireControlPermission(
    CONTROL_PERM_SYSTEM_SETTINGS,
    'view_control_system_settings',
    CONTROL_PERM_DASHBOARD
);

$controlCenterBase = rtrim((defined('SITE_URL') ? SITE_URL : ''), '/');
if ($controlCenterBase === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $controlCenterBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
}
$target = rtrim((string) $controlCenterBase, '/') . '/admin/control-center.php#system-flags';
header('Location: ' . $target, true, 302);
exit;
