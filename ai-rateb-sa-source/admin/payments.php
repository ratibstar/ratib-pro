<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

$page = admin_page();
$total = (int) $pdo->query('SELECT COUNT(*) FROM payment_receipts')->fetchColumn();
$sum = (int) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payment_receipts')->fetchColumn();
$stmt = $pdo->prepare('SELECT r.created_at, r.moyasar_id, r.package_type, r.amount, u.email, u.name, u.id AS user_id
    FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id
    ORDER BY r.id DESC LIMIT ' . admin_per_page() . ' OFFSET ' . admin_offset($page));
$stmt->execute();
$rows = $stmt->fetchAll();

admin_start(admin_t('Payments', 'المدفوعات'));
admin_crud_toolbar([
    'screen' => 'payments',
    'can_manage' => false,
    'add_href' => '/admin/refunds.php',
    'links' => [
        ['href' => '/admin/finance.php', 'label' => admin_t('Finance hub', 'مركز المالية')],
    ],
]);
admin_panel_open(admin_t('Payment receipts', 'إيصالات الدفع'));
echo '<section class="stats"><article class="stat"><b>' . admin_h(admin_halalas_to_sar($sum)) . '</b><span>' . admin_h(admin_t('Confirmed revenue SAR', 'إيراد مؤكد بالريال')) . '</span></article>';
echo '<article class="stat"><b>' . (int) $total . '</b><span>' . admin_h(admin_t('Receipts', 'الإيصالات')) . '</span></article></section>';
echo '<p class="note">' . admin_h(admin_t(
    'A row exists only after the webhook re-fetched the Moyasar payment and saw status paid. Currency is SAR. Amount is stored in halalas. There is no refund record in this database. The callback URL still carries the Moyasar secret; that flow was not changed in this release.',
    'يُنشأ الصف بعد أن يعيد الويب هوك جلب عملية Moyasar ويتأكد أن حالتها paid. العملة ريال. المبلغ بالهللة. لا يوجد سجل استرداد. رابط الاستدعاء ما زال يحمل سر Moyasar، ولم يُغيّر مسار الدفع في هذا الإصدار.'
)) . '</p>';
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Credits in code', 'النقاط في الكود')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $grant = admin_known_payment_credits((string) $row['package_type']);
    $uid = (int) $row['user_id'];
    $editHref = '/admin/finance-customer.php?id=' . $uid;
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td><a href="/admin/user.php?id=' . $uid . '">' . admin_h((string) $row['name']) . '</a><br>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['moyasar_id']) . '</td><td>' . admin_h((string) $row['package_type']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td><span class="pill ok">paid</span></td><td>' . ($grant === null ? '—' : (int) $grant) . '</td><td>';
    admin_crud_row_actions($editHref, '/admin/user.php?id=' . $uid, false, $uid);
    echo '</td></tr>';
}
admin_table_close();
admin_pager($page, $total, '/admin/payments.php?page=');
echo '<p class="note">' . admin_h(admin_t(
    'The webhook adds 500 credits for starter and 2500 for pro, then writes usage_events with credits_deducted = 0. The number above is that code map, not a stored grant amount.',
    'يضيف الويب هوك 500 نقطة للأساسية و2500 للاحترافية، ثم يكتب usage_events بقيمة credits_deducted = 0. الرقم أعلاه من خريطة الكود وليس مبلغًا مخزنًا.'
)) . '</p>';
admin_panel_close();
admin_end();
