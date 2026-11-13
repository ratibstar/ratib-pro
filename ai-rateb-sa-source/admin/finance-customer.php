<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

$id = (int) ($_GET['id'] ?? 0);
$disabledCol = admin_table_has($pdo, 'users', 'disabled_at');
$hasOrders = admin_table_has($pdo, 'payment_orders', 'status');

$disabledExpr = $disabledCol ? 'disabled_at' : 'NULL AS disabled_at';
$stmt = $pdo->prepare("SELECT id, name, email, credits_balance, package_type, created_at, $disabledExpr FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch();

admin_start(admin_t('Customer finances', 'مالية العميل'));
if (!$user) {
    echo '<p class="note">' . admin_h(admin_t('Customer not found.', 'العميل غير موجود.')) . '</p>';
    echo '<p><a class="btn" href="/admin/finance.php?tab=customers">' . admin_h(admin_t('Back to customers', 'العودة للعملاء')) . '</a></p>';
    admin_end();
    exit;
}

$paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS halalas, COUNT(*) AS paid_count, MAX(created_at) AS last_payment FROM payment_receipts WHERE user_id = ?');
$paidStmt->execute([$id]);
$paid = $paidStmt->fetch() ?: ['halalas' => 0, 'paid_count' => 0, 'last_payment' => null];

$planStmt = $pdo->prepare("SELECT p.code, p.name_ar, p.name_en, p.price_sar, s.status, s.period_end
    FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id
    WHERE s.user_id = ? AND s.status = 'active' AND s.period_end >= NOW()
    ORDER BY s.id DESC LIMIT 1");
$planStmt->execute([$id]);
$plan = $planStmt->fetch();

$disabled = ($user['disabled_at'] ?? null) !== null && (string) $user['disabled_at'] !== '';

echo '<p><a class="btn quiet" href="/admin/finance.php?tab=customers">' . admin_h(admin_t('Back to customers', 'العودة للعملاء')) . '</a> <a class="btn quiet" href="/admin/user.php?id=' . (int) $id . '">' . admin_h(admin_t('Account profile', 'ملف الحساب')) . '</a></p>';

echo '<section class="cards"><article class="card"><h2>' . admin_h((string) $user['name']) . '</h2>';
echo '<p>' . admin_h((string) $user['email']) . '</p>';
echo '<p>' . admin_h(admin_t('Successfully paid (SAR)', 'مدفوع بنجاح (ر.س)')) . ': <b>' . admin_h(admin_halalas_to_sar((int) $paid['halalas'])) . '</b> · ' . (int) $paid['paid_count'] . ' ' . admin_h(admin_t('receipts', 'إيصال')) . '</p>';
echo '<p>' . admin_h(admin_t('Credit balance', 'رصيد النقاط')) . ': ' . (int) $user['credits_balance'] . '</p>';
echo '<p>' . admin_h(admin_t('Last payment', 'آخر دفع')) . ': ' . admin_h((string) ($paid['last_payment'] ?? '—')) . '</p>';
echo '<p><span class="pill ' . ($disabled ? 'bad' : 'ok') . '">' . admin_h($disabled ? admin_t('Disabled', 'معطّل') : admin_t('Active', 'نشط')) . '</span></p>';
if ($plan) {
    $planLabel = $adminLang === 'ar' ? (string) $plan['name_ar'] : (string) $plan['name_en'];
    echo '<p>' . admin_h(admin_t('Active subscription (catalog)', 'اشتراك نشط (كتالوج)')) . ': ' . admin_h((string) $plan['code']) . ' — ' . admin_h($planLabel) . ' · ' . admin_h(admin_t('Catalog price', 'سعر الكتالوج')) . ' ' . admin_h((string) $plan['price_sar']) . ' SAR</p>';
} else {
    echo '<p>' . admin_h(admin_t('Active subscription', 'اشتراك نشط')) . ': —</p>';
}
echo '</article></section>';

echo '<h2 class="section-title">' . admin_h(admin_t('Successful payment history', 'سجل المدفوعات الناجحة')) . '</h2>';
$history = $pdo->prepare('SELECT id, created_at, moyasar_id, package_type, amount FROM payment_receipts WHERE user_id = ? ORDER BY id DESC');
$history->execute([$id]);
$receipts = $history->fetchAll();
echo '<table><tr><th>' . admin_h(admin_t('Receipt', 'إيصال')) . '</th><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Moyasar ID', 'معرّف Moyasar')) . '</th></tr>';
foreach ($receipts as $row) {
    echo '<tr><td>R' . (int) $row['id'] . '</td><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['package_type']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td>' . admin_h((string) $row['moyasar_id']) . '</td></tr>';
}
if ($receipts === []) {
    echo '<tr><td colspan="5">' . admin_h(admin_t('No successful payments yet.', 'لا توجد مدفوعات ناجحة بعد.')) . '</td></tr>';
}
echo '</table>';

if ($hasOrders) {
    echo '<h2 class="section-title">' . admin_h(admin_t('Pending and failed orders', 'طلبات معلّقة وفاشلة')) . '</h2>';
    $orders = $pdo->prepare("SELECT id, created_at, package_code, amount, status, COALESCE(moyasar_invoice_id,'') AS moyasar_ref FROM payment_orders WHERE user_id = ? AND status IN ('pending','failed') ORDER BY id DESC");
    $orders->execute([$id]);
    $orderRows = $orders->fetchAll();
    echo '<table><tr><th>' . admin_h(admin_t('Order', 'طلب')) . '</th><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Moyasar invoice', 'فاتورة Moyasar')) . '</th></tr>';
    foreach ($orderRows as $row) {
        $pill = (string) $row['status'] === 'pending' ? 'warn' : 'bad';
        echo '<tr><td>O' . (int) $row['id'] . '</td><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['package_code']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td><span class="pill ' . $pill . '">' . admin_h((string) $row['status']) . '</span></td><td>' . admin_h((string) $row['moyasar_ref']) . '</td></tr>';
    }
    if ($orderRows === []) {
        echo '<tr><td colspan="6">' . admin_h(admin_t('No pending or failed orders.', 'لا توجد طلبات معلّقة أو فاشلة.')) . '</td></tr>';
    }
}

echo '<p class="note">' . admin_h(admin_t(
    'Receipt amounts are the only collected cash. Order rows are not revenue. This application records SAR; halalas are converted for display.',
    'مبالغ الإيصالات هي النقد المحصّل فقط. صفوف الطلبات ليست إيرادًا. التطبيق يسجّل الريال ويُحوَّل من الهللة للعرض.'
)) . '</p>';
admin_end();
