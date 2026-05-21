<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/home.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/home.php`.
 */
/**
 * Client Hub / plans entry inside the main platform ecosystem.
 */
require_once __DIR__ . '/../includes/config.php';
$open = $_GET['open'] ?? '';
$siteRoot = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
$platformUrl = $siteRoot !== '' ? ($siteRoot . '/pages/home.php') : null;
if ($platformUrl && ($open === 'register' || $open === '')) {
    $query = $_GET;
    if (!isset($query['open']) || (string) $query['open'] === '') {
        $query['open'] = 'register';
    }
    $qs = http_build_query($query);
    header('Location: ' . $platformUrl . ($qs !== '' ? ('?' . $qs) : ''));
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Client Hub &amp; Services</title><link rel="stylesheet" href="<?php echo asset('css/control/home.css'); ?>?v=<?php echo time(); ?>"></head>
<body class="home-register-body">
<p>Client Hub plans and service onboarding live in the main RATEB platform experience. Set <code>SITE_URL</code> so this page can redirect into the unified customer flow automatically.</p>
</body></html>
