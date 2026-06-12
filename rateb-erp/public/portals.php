<?php
declare(strict_types=1);

/**
 * RATEB ERP — portal links (no Control Panel login required).
 */
require_once dirname(__DIR__) . '/config/app.php';

$origin = rateb_site_origin();
$links = [
    ['بوابة الشركة (إضافة مشتريات ومخزون)', 'company/login', 'fa-building', 'primary'],
    ['دخول الإدارة (Super Admin)', 'admin/login', 'fa-user-shield', 'secondary'],
    ['لوحة الإدارة', 'admin', 'fa-chart-line', 'outline'],
    ['المشتريات — مراقبة', 'admin/procurement', 'fa-cart-shopping', 'outline'],
    ['المخزون — مراقبة', 'admin/inventory', 'fa-boxes-stacked', 'outline'],
    ['الشركات', 'admin/companies', 'fa-building', 'outline'],
    ['المستخدمون', 'admin/users', 'fa-users', 'outline'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>روابط نظام رتب ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: Tajawal, system-ui, sans-serif; }
        .card { background: #1e293b; border: 1px solid #334155; }
        code { direction: ltr; display: block; background: #0f172a; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; }
    </style>
</head>
<body class="p-4">
<div class="container" style="max-width: 720px;">
    <h1 class="h3 mb-2"><i class="fas fa-hospital text-info"></i> نظام رتب ERP — روابط مباشرة</h1>
    <p class="text-secondary mb-4">تعمل <strong>بدون</strong> تسجيل دخول لوحة التحكم. سجّل دخول ERP فقط (إدارة أو شركة).</p>
    <div class="d-grid gap-2 mb-4">
        <?php foreach ($links as [$label, $route, $icon, $btn]) {
            $href = rateb_public_url($route);
            $cls = $btn === 'primary' ? 'btn-primary btn-lg' : ($btn === 'secondary' ? 'btn-secondary btn-lg' : 'btn-outline-light');
            ?>
        <a class="btn <?php echo $cls; ?> text-start" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> me-2"></i><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="card p-3">
        <h2 class="h6">انسخ الروابط</h2>
        <p class="small text-secondary mb-2">بوابة الشركة (للعمل اليومي):</p>
        <code><?php echo htmlspecialchars(rateb_public_url('company/login'), ENT_QUOTES, 'UTF-8'); ?></code>
        <p class="small text-secondary mb-2 mt-3">إدارة النظام:</p>
        <code><?php echo htmlspecialchars(rateb_public_url('admin/login'), ENT_QUOTES, 'UTF-8'); ?></code>
    </div>
</div>
</body>
</html>
