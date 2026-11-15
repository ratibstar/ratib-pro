<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../nav.php';
require_login();

$orderId = (int) ($_GET['order'] ?? 0);
$campaignId = (int) ($_GET['campaign'] ?? 0);
$pdo = db();
$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare('SELECT id, status, package_code, amount FROM payment_orders WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$orderId, (int) $_SESSION['user_id']]);
    $order = $stmt->fetch() ?: null;
}

$receipt = null;
if ($order && (string) $order['status'] === 'paid') {
    $r = $pdo->prepare('SELECT id FROM payment_receipts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $r->execute([(int) $_SESSION['user_id']]);
    $receipt = $r->fetch() ?: null;
}

$ar = ui_language() === 'ar';
$status = $order ? (string) $order['status'] : 'unknown';
$title = match ($status) {
    'paid' => $ar ? 'تم الدفع' : 'Payment confirmed',
    'failed' => $ar ? 'فشل الدفع' : 'Payment failed',
    'pending' => $ar ? 'الدفع قيد التأكيد' : 'Payment pending',
    default => $ar ? 'حالة الدفع' : 'Payment status',
};
echo '<!doctype html><html lang="' . ($ar ? 'ar' : 'en') . '" dir="' . ($ar ? 'rtl' : 'ltr') . '" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>RATEB AI — ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
echo '<link rel="stylesheet" href="/public/assets/css/theme.css"><link rel="stylesheet" href="/public/assets/css/app.css?v=nav44"></head><body class="studio">';
rateb_site_header('/billing.php', true);
echo '<main class="container studio-page" style="max-width:640px;margin:32px auto">';
echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
if ($status === 'paid') {
    echo '<p>' . ($ar ? 'شكراً. تم تسجيل الدفع.' : 'Thank you. Your payment is recorded.') . '</p>';
    if ($receipt) {
        echo '<p><a class="button" href="/app/payment/invoice.php?id=' . (int) $receipt['id'] . '">' . ($ar ? 'عرض الفاتورة' : 'View invoice') . '</a></p>';
    }
    echo '<p><a href="/billing.php">' . ($ar ? 'الفواتير والاشتراك' : 'Billing') . '</a></p>';
    if ($campaignId > 0) {
        echo '<p><a href="/app/campaign/package.php?id=' . $campaignId . '">' . ($ar ? 'العودة للحملة' : 'Back to campaign') . '</a></p>';
    } else {
        echo '<p><a href="/dashboard">' . ($ar ? 'لوحة التحكم' : 'Dashboard') . '</a></p>';
    }
} elseif ($status === 'failed') {
    echo '<p class="alert">' . ($ar ? 'لم تكتمل العملية. يمكنك المحاولة مرة أخرى.' : 'The payment did not complete. You can try again.') . '</p>';
    echo '<p><a class="button" href="/credits.php">' . ($ar ? 'العودة للباقات' : 'Back to plans') . '</a></p>';
} else {
    echo '<p>' . ($ar ? 'قد يستغرق التأكيد دقيقة عبر ميسر. حدّث الصفحة أو افتح الفواتير.' : 'Confirmation may take a minute via Moyasar. Refresh or open billing.') . '</p>';
    echo '<p><a class="button" href="/billing.php">' . ($ar ? 'الفواتير' : 'Billing') . '</a> · <a href="#" onclick="location.reload();return false">' . ($ar ? 'تحديث' : 'Refresh') . '</a></p>';
}
echo '</main></body></html>';