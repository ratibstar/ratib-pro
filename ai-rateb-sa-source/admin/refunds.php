<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/payment/commerce.php';

admin_require_permission('finance.view');

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!admin_actor_is_owner($adminActor)) {
        $notice = 'owner_only';
    } else {
        $rawId = trim((string) ($_POST['receipt_id'] ?? ''));
        $receiptId = 0;
        if (preg_match('/^R?(\d+)$/i', $rawId, $m)) {
            $receiptId = (int) $m[1];
        }
        $sar = (float) ($_POST['amount_sar'] ?? 0);
        $amount = max(0, (int) round($sar * 100));
        $credits = max(0, (int) ($_POST['credits_reversed'] ?? 0));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $result = rateb_record_payment_refund($pdo, $receiptId, $amount, $credits, $reason, 'admin');
        $notice = $result === 'applied' ? 'saved' : ($result === 'duplicate' ? 'duplicate' : 'missing');
        if ($result === 'applied') {
            admin_audit($pdo, $adminActor, 'payment_refund', 'payment_refunds', $receiptId, 'owner', 'amount=' . $amount);
        }
    }
}

$rows = $pdo->query('SELECT f.id, f.created_at, f.amount_halalas, f.credits_reversed, f.reason, f.source, r.id AS receipt_id, u.name, u.email
    FROM payment_refunds f
    INNER JOIN payment_receipts r ON r.id = f.receipt_id
    INNER JOIN users u ON u.id = f.user_id
    ORDER BY f.id DESC LIMIT 80')->fetchAll();

admin_start(admin_t('Refunds', 'الاستردادات'));
admin_toolbar([
    ['href' => '/admin/finance.php', 'label' => admin_t('Finance hub', 'مركز المالية')],
    ['href' => '/admin/payments.php', 'label' => admin_t('Receipts', 'الإيصالات')],
]);
if ($notice === 'saved') {
    admin_flash_box('saved', 'ok');
} elseif ($notice === 'owner_only') {
    admin_flash_box('owner_only', 'bad');
} elseif ($notice === 'duplicate') {
    echo '<p class="flash">' . admin_h(admin_t('Duplicate refund skipped.', 'تخطي استرداد مكرر.')) . '</p>';
} elseif ($notice === 'missing') {
    echo '<p class="flash bad">' . admin_h(admin_t('Invalid receipt or amount.', 'إيصال أو مبلغ غير صالح.')) . '</p>';
}

if (admin_actor_is_owner($adminActor)) {
    admin_panel_open(admin_t('Record refund', 'تسجيل استرداد'), admin_t(
        'Use when Moyasar or support refunded a customer. Credits reversed is optional (defaults from package on webhook).',
        'استخدم عند استرداد من Moyasar أو الدعم. عكس النقاط اختياري (الويب هوك يستخدم خريطة الباقة).'
    ));
    echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
    echo '<label>' . admin_h(admin_t('Receipt id', 'رقم الإيصال R')) . '<input name="receipt_id" inputmode="numeric" required placeholder="R123"></label>';
    echo '<label>SAR<input name="amount_sar" inputmode="decimal" required></label>';
    echo '<label>' . admin_h(admin_t('Credits to reverse', 'نقاط تُخصم')) . '<input name="credits_reversed" inputmode="numeric" value="0"></label>';
    echo '<label class="full">' . admin_h(admin_t('Reason', 'السبب')) . '<input name="reason" required minlength="3"></label>';
    echo '<p class="full"><button type="submit" class="btn primary">' . admin_h(admin_t('Save refund', 'حفظ الاسترداد')) . '</button></p></form>';
    admin_panel_close();
}

admin_panel_open(admin_t('Refund ledger', 'سجل الاستردادات'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Receipt', 'إيصال')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Credits', 'نقاط')) . '</th><th>' . admin_h(admin_t('Source', 'المصدر')) . '</th><th>' . admin_h(admin_t('Reason', 'السبب')) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['name']) . '</td>';
    echo '<td>R' . (int) $row['receipt_id'] . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount_halalas'])) . '</td>';
    echo '<td>' . (int) $row['credits_reversed'] . '</td><td>' . admin_h((string) $row['source']) . '</td><td>' . admin_h((string) ($row['reason'] ?? '')) . '</td></tr>';
}
if ($rows === []) {
    admin_table_empty_row(7, admin_t('No refunds yet.', 'لا استردادات بعد.'));
}
admin_table_close();
admin_panel_close();
admin_end();