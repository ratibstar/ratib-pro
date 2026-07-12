<?php
declare(strict_types=1);

/**
 * RATEB ERP — portal links (no Control Panel login required).
 */
require_once dirname(__DIR__) . '/config/app.php';

$loginUrl = rateb_public_url('login');
$links = [
    ['تسجيل الدخول — رابط واحد', 'login', 'fa-right-to-bracket', 'primary'],
    ['لوحة النظام (بعد الدخول)', 'admin', 'fa-chart-line', 'outline'],
    ['طلبات الشراء', rateb_app_route('purchase-requests'), 'fa-file-circle-plus', 'outline'],
    ['المخزون', rateb_app_route('inventory'), 'fa-boxes-stacked', 'outline'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>روابط نظام رتب ERP</title>
    <link href="<?php echo rateb_bootstrap_css(); ?>" rel="stylesheet">
    <link href="<?php echo rateb_fontawesome_css(); ?>" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: Tajawal, system-ui, sans-serif; }
        .card { background: #1e293b; border: 1px solid #334155; }
        code { direction: ltr; display: block; background: #0f172a; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; }
    </style>
</head>
<body class="p-4">
<div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-2"><i class="fas fa-hospital text-info"></i> نظام رتب ERP</h1>
    <p class="text-secondary mb-4">رابط دخول <strong>واحد</strong> — بعد الدخول تفتح لوحة النظام الموحّدة (<code>/admin</code>) وتظهر القوائم حسب صلاحيات حسابك.</p>
    <div class="d-grid gap-2 mb-4">
        <?php foreach ($links as [$label, $route, $icon, $btn]) {
            $href = rateb_public_url($route);
            $cls = $btn === 'primary' ? 'btn-primary btn-lg' : 'btn-outline-light';
            ?>
        <a class="btn <?php echo $cls; ?> text-start" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="card p-3">
        <h2 class="h6">رابط الدخول الموحّد</h2>
        <code><?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?></code>
    </div>
</div>
</body>
</html>
