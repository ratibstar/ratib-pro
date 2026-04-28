<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/home.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/home.php`.
 */
/**
 * Stub: Register Pro / home. Redirects to Ratib Pro when RATIB_PRO_URL is set.
 */
require_once __DIR__ . '/../includes/config.php';
$open = $_GET['open'] ?? '';
$ratibUrl = defined('RATIB_PRO_URL') ? RATIB_PRO_URL : null;
if ($ratibUrl && ($open === 'register' || $open === '')) {
    header('Location: ' . rtrim($ratibUrl, '/') . '/pages/home.php' . ($open ? '?open=' . urlencode($open) : ''));
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Register Pro</title><link rel="stylesheet" href="<?php echo asset('css/control/home.css'); ?>?v=<?php echo time(); ?>"></head>
<body class="home-register-body">
<p>Register Pro is available on Ratib Pro. Set <code>RATIB_PRO_URL</code> in <code>config/env.php</code> to redirect there automatically.</p>
</body></html>
