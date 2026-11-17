<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

$page = admin_page();
$total = (int) $pdo->query('SELECT COUNT(*) FROM payment_receipts')->fetchColumn();
$page = admin_enforce_page_in_range('/admin/payments.php', $page, $total);
$sum = (int) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payment_receipts')->fetchColumn();
$stmt = $pdo->prepare('SELECT r.id, r.created_at, r.moyasar_id, r.package_type, r.amount, u.email, u.name, u.id AS user_id
    FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id
    ORDER BY r.id DESC LIMIT ' . admin_per_page() . ' OFFSET ' . admin_offset($page));
$stmt->execute();
$rows = $stmt->fetchAll();

admin_start(admin_t('Payments', 'المدفوعات'));
admin_finance_nav_toolbar($adminActor);
admin_panel_open(admin_t('Payment receipts', 'إيصالات الدفع'));
$canDelete = admin_actor_is_owner($adminActor);
$listReturn = '/admin/payments.php?page=' . $page;
admin_bulk_flash_from_query();
$formId = admin_bulk_form_open('finance_receipts', $listReturn);
admin_crud_toolbar([
    'screen' => 'finance_receipts',
    'can_manage' => $canDelete,
    'form_id' => $formId,
    'add_enabled' => false,
]);
echo '<section class="stats"><article class="stat"><b>' . admin_h(admin_halalas_to_sar($sum)) . '</b><span>' . admin_h(admin_t('Confirmed revenue SAR', 'إيراد مؤكد بالريال')) . '</span></article>';
echo '<article class="stat"><b>' . (int) $total . '</b><span>' . admin_h(admin_t('Receipts', 'الإيصالات')) . '</span></article></section>';
echo '<p class="note">' . admin_h(admin_t(
    'A row exists only after the webhook re-fetched the Moyasar payment and saw status paid. Currency is SAR. Amount is stored in halalas. There is no refund record in this database. The callback URL still carries the Moyasar secret; that flow was not changed in this release.',
    'يُنشأ الصف بعد أن يعيد الويب هوك جلب عملية Moyasar ويتأكد أن حالتها paid. العملة ريال. المبلغ بالهللة. لا يوجد سجل استرداد. رابط الاستدعاء ما زال يحمل سر Moyasar، ولم يُغيّر مسار الدفع في هذا الإصدار.'
)) . '</p>';
admin_table_open();
echo '<tr>';
admin_th_select($canDelete);
echo '<th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Credits in code', 'النقاط في الكود')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $grant = admin_known_payment_credits((string) $row['package_type']);
    $uid = (int) $row['user_id'];
    $receiptId = (int) ($row['id'] ?? 0);
    $editHref = '/admin/finance-customer.php?id=' . $uid;
    $refundCnt = $pdo->prepare('SELECT COUNT(*) FROM payment_refunds WHERE receipt_id = ?');
    $refundCnt->execute([$receiptId]);
    $rowDeletable = $canDelete && $receiptId > 0 && (int) $refundCnt->fetchColumn() === 0;
    echo '<tr>';
    admin_td_select($receiptId, $editHref, $rowDeletable);
    echo '<td>' . admin_h((string) $row['created_at']) . '</td><td><a href="/admin/user.php?id=' . $uid . '">' . admin_h((string) $row['name']) . '</a><br>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['moyasar_id']) . '</td><td>' . admin_h((string) $row['package_type']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td><span class="pill ok">paid</span></td><td>' . ($grant === null ? '—' : (int) $grant) . '</td><td>';
    admin_crud_row_actions($editHref, '/admin/user.php?id=' . $uid, $rowDeletable, $receiptId, $rowDeletable);
    echo '</td></tr>';
}
admin_table_close();
admin_bulk_form_close();
admin_pager($page, $total, '/admin/payments.php?page=');
echo '<p class="note">' . admin_h(admin_t(
    'The webhook adds 500 credits for starter and 2500 for pro, then writes usage_events with credits_deducted = 0. The number above is that code map, not a stored grant amount.',
    'يضيف الويب هوك 500 نقطة للأساسية و2500 للاحترافية، ثم يكتب usage_events بقيمة credits_deducted = 0. الرقم أعلاه من خريطة الكود وليس مبلغًا مخزنًا.'
)) . '</p>';
admin_panel_close();
admin_end();
