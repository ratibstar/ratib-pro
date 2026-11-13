<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('customers.view');

$id = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    admin_require_permission('customers.manage');
    $action = (string) ($_POST['action'] ?? '');
    $reason = (string) ($_POST['reason'] ?? '');
    if (!admin_table_has($pdo, 'users', 'disabled_at') || !admin_table_has($pdo, 'admin_audit_log', 'id') || !admin_table_has($pdo, 'admin_credit_adjustments', 'id')) {
        $notice = 'schema';
    } elseif ($action === 'credits') {
        if (!admin_actor_is_owner($adminActor)) {
            $notice = 'invalid';
        } else {
            $delta = (int) ($_POST['delta'] ?? 0);
            $key = strtolower((string) ($_POST['idempotency_key'] ?? ''));
            $result = admin_adjust_credits($pdo, $adminActor, $id, $delta, $reason, $key);
            $notice = (string) $result['code'];
        }
    } elseif ($action === 'disable' || $action === 'enable') {
        $result = admin_set_account_state($pdo, $adminActor, $id, $action === 'disable', $reason);
        $notice = (string) $result['code'];
    } else {
        $notice = 'invalid';
    }
}

$login = admin_table_has($pdo, 'users', 'last_login_at') ? 'last_login_at' : 'NULL AS last_login_at';
$disabledCol = admin_table_has($pdo, 'users', 'disabled_at') ? 'disabled_at' : 'NULL AS disabled_at';
$stmt = $pdo->prepare("SELECT id, name, email, role, package_type, credits_balance, subscription_expires_at, created_at, updated_at, $login, $disabledCol FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
admin_start(admin_t('User', 'مستخدم'));
if (!$user) {
    echo '<p class="note">' . admin_h(admin_t('User not found.', 'المستخدم غير موجود.')) . '</p>';
    admin_end();
    exit;
}
if ($notice !== '') {
    echo '<p class="flash">' . admin_h(admin_flash($notice)) . '</p>';
}
echo '<section class="cards"><article class="card"><h2>' . admin_h((string) $user['name']) . '</h2>';
echo '<p>' . admin_h((string) $user['email']) . '</p>';
echo '<p>' . admin_h(admin_t('Role', 'الدور')) . ': ' . admin_h((string) $user['role']) . ' · ' . admin_h(admin_t('Package', 'الباقة')) . ': ' . admin_h((string) $user['package_type']) . '</p>';
echo '<p>' . admin_h(admin_t('Credits', 'النقاط')) . ': ' . (int) $user['credits_balance'] . '</p>';
if (admin_table_has($pdo, 'payment_receipts', 'amount') && admin_actor_has($adminActor, 'finance.view')) {
    $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_receipts WHERE user_id = ?');
    $paidStmt->execute([$id]);
    $paidHalalas = (int) $paidStmt->fetchColumn();
    echo '<p>' . admin_h(admin_t('Total paid (successful)', 'إجمالي المدفوع (ناجح)')) . ': <b>' . admin_h(admin_halalas_to_sar($paidHalalas)) . ' SAR</b></p>';
    echo '<p><a class="btn" href="/admin/finance-customer.php?id=' . (int) $id . '">' . admin_h(admin_t('Payment history & subscriptions', 'سجل الدفع والاشتراكات')) . '</a></p>';
}
echo '<p>' . admin_h(admin_t('Registered', 'التسجيل')) . ': ' . admin_h((string) $user['created_at']) . '</p>';
echo '<p>' . admin_h(admin_t('Last login', 'آخر دخول')) . ': ' . admin_h((string) ($user['last_login_at'] ?? admin_t('Not tracked', 'غير مسجّل'))) . '</p>';
echo '<p>' . admin_h(admin_t('Subscription field', 'حقل انتهاء الاشتراك')) . ': ' . admin_h((string) ($user['subscription_expires_at'] ?? '—')) . '</p>';
$disabled = ($user['disabled_at'] ?? null) !== null && (string) $user['disabled_at'] !== '';
echo '<p class="pill ' . ($disabled ? 'bad' : 'ok') . '">' . admin_h($disabled ? admin_t('Disabled', 'معطّل') : admin_t('Enabled', 'مفعّل')) . '</p></article></section>';

if ((string) $user['role'] !== 'owner') {
    if (admin_user_credit_form_visible($adminActor, $user)) {
        $key = bin2hex(random_bytes(16));
        echo '<h2>' . admin_h(admin_t('Credits', 'النقاط')) . '</h2>';
        echo '<form class="stack" method="post"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="user_id" value="' . (int) $user['id'] . '"><input type="hidden" name="action" value="credits"><input type="hidden" name="idempotency_key" value="' . admin_h($key) . '">';
        echo '<label>' . admin_h(admin_t('Change (negative deducts)', 'التغيير (السالب يخصم)')) . '</label><input name="delta" inputmode="numeric" required>';
        echo '<label>' . admin_h(admin_t('Reason', 'السبب')) . '</label><textarea name="reason" required minlength="3" maxlength="500"></textarea>';
        echo '<button type="submit">' . admin_h(admin_t('Apply', 'تطبيق')) . '</button></form>';
    }
    echo '<h2>' . admin_h($disabled ? admin_t('Enable account', 'تفعيل الحساب') : admin_t('Disable account', 'تعطيل الحساب')) . '</h2>';
    echo '<form class="stack" method="post"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="user_id" value="' . (int) $user['id'] . '"><input type="hidden" name="action" value="' . ($disabled ? 'enable' : 'disable') . '">';
    echo '<textarea name="reason" required minlength="3" maxlength="500"></textarea><button type="submit">' . admin_h(admin_t('Save', 'حفظ')) . '</button></form>';
}

$campaigns = $pdo->prepare('SELECT id, title, status, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$campaigns->execute([$id]);
echo '<h2>' . admin_h(admin_t('Campaigns', 'الحملات')) . '</h2><table><tr><th>' . admin_h(admin_t('Title', 'العنوان')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Created', 'التاريخ')) . '</th></tr>';
foreach ($campaigns as $row) {
    echo '<tr><td><a href="/admin/campaign.php?id=' . (int) $row['id'] . '">' . admin_h((string) $row['title']) . '</a></td><td>' . admin_h((string) $row['status']) . '</td><td>' . admin_h((string) $row['created_at']) . '</td></tr>';
}
echo '</table>';

$usage = $pdo->prepare('SELECT created_at, operation, status, units, credits_deducted, campaign_id FROM usage_events WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$usage->execute([$id]);
echo '<h2>' . admin_h(admin_t('Usage and credit events', 'الاستخدام وحركات النقاط')) . '</h2><table><tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Operation', 'العملية')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Credits recorded', 'النقاط المسجّلة')) . '</th><th>' . admin_h(admin_t('Campaign', 'الحملة')) . '</th></tr>';
foreach ($usage as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . (int) $row['credits_deducted'] . '</td><td>' . admin_h((string) ($row['campaign_id'] ?? '')) . '</td></tr>';
}
echo '</table>';

$pays = $pdo->prepare('SELECT created_at, moyasar_id, package_type, amount FROM payment_receipts WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$pays->execute([$id]);
echo '<h2>' . admin_h(admin_t('Payments', 'المدفوعات')) . '</h2><table><tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th></tr>';
foreach ($pays as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['moyasar_id']) . '</td><td>' . admin_h((string) $row['package_type']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td><span class="pill ok">paid</span></td></tr>';
}
echo '</table>';

$subs = $pdo->prepare("SELECT s.status, s.period_start, s.period_end, p.code FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? ORDER BY s.id DESC LIMIT 50");
$subs->execute([$id]);
echo '<h2>' . admin_h(admin_t('Subscriptions', 'الاشتراكات')) . '</h2><table><tr><th>' . admin_h(admin_t('Plan', 'الباقة')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Start', 'البداية')) . '</th><th>' . admin_h(admin_t('End', 'النهاية')) . '</th></tr>';
foreach ($subs as $row) {
    echo '<tr><td>' . admin_h((string) $row['code']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . admin_h((string) $row['period_start']) . '</td><td>' . admin_h((string) $row['period_end']) . '</td></tr>';
}
echo '</table>';

if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
    $auditRows = admin_audit_rows_for_target($pdo, 'user', $id, 50);
    $missingStaff = admin_t('Staff (removed)', 'موظف (محذوف)');
    echo '<h2>' . admin_h(admin_t('Admin audit', 'سجل الإدارة')) . '</h2><table><tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Actor', 'الفاعل')) . '</th><th>' . admin_h(admin_t('Action', 'الإجراء')) . '</th><th>' . admin_h(admin_t('Reason', 'السبب')) . '</th></tr>';
    foreach ($auditRows as $row) {
        $actorLabel = admin_audit_actor_display($row, $missingStaff);
        echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h($actorLabel) . '</td><td>' . admin_h((string) $row['action']) . '</td><td>' . admin_h((string) $row['reason']) . '</td></tr>';
    }
    echo '</table>';
}
admin_end();
