<?php
declare(strict_types=1);

require_once __DIR__ . '/app/brand/library.php';
require_once __DIR__ . '/app/nav.php';
require_login();

$roleStmt = db()->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$roleStmt->execute([(int) $_SESSION['user_id']]);
$role = (string) ($roleStmt->fetchColumn() ?: '');
if ($role !== 'owner') {
    header('Location: /dashboard');
    exit;
}

$brand = brand_owned();
$error = (string) ($_GET['brand_error'] ?? '');
if (!in_array($error, ['size', 'type', 'upload'], true)) {
    $error = '';
}
?><!doctype html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RATEB AI — هوية العلامة</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="/public/assets/css/theme.css">
<link rel="stylesheet" href="/public/assets/css/app.css?v=nav66">
</head>
<body class="studio page-brand">
<?php rateb_site_header('/brand.php', true); ?>
<main class="wrap">
  <section class="panel">
    <span class="eyebrow">إدارة</span>
    <h1>هوية العلامة</h1>
    <p class="muted">لطاقم RATEB فقط. تُضبط هوية كل عميل من صفحة المستخدم في لوحة الإدارة.</p>
    <p><a class="button" href="/admin/users.php">فتح لوحة الإدارة</a></p>
  </section>
</main>
<script src="/public/assets/js/app.js?v=nav66"></script>
</body>
</html>
