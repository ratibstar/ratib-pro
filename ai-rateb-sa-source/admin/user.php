<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/brand/library.php';

admin_require_permission('customers.view');

$id = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    admin_require_permission('customers.manage');
    $action = (string) ($_POST['action'] ?? '');
    $reason = (string) ($_POST['reason'] ?? '');
    if ($action === 'brand') {
        if (!admin_table_has($pdo, 'brand_kits', 'user_id')) {
            $notice = 'schema';
        } else {
            $current = brand_for_user($id);
            $logoStored = $current['logo_stored'] ?? null;
            $logoMime = $current['logo_mime'] ?? null;
            try {
                if (!empty($_FILES['logo']['name'])) {
                    $saved = brand_store_logo($_FILES['logo'], $id);
                    if ($saved['stored']) {
                        $logoStored = $saved['stored'];
                        $logoMime = $saved['mime'];
                    }
                }
                $language = (string) ($_POST['preferred_language'] ?? 'ar');
                if ($language !== 'ar' && $language !== 'en') {
                    $language = 'ar';
                }
                $dialect = (string) ($_POST['dialect'] ?? '');
                if (!in_array($dialect, ['', 'najdi', 'hijazi', 'khaleeji', 'fusha'], true)) {
                    $dialect = '';
                }
                $stmt = $pdo->prepare('INSERT INTO brand_kits (user_id, brand_name, logo_stored, logo_mime, primary_color, secondary_color, preferred_language, tone, contact, audience, offer_line, forbidden, dialect) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name), logo_stored=VALUES(logo_stored), logo_mime=VALUES(logo_mime), primary_color=VALUES(primary_color), secondary_color=VALUES(secondary_color), preferred_language=VALUES(preferred_language), tone=VALUES(tone), contact=VALUES(contact), audience=VALUES(audience), offer_line=VALUES(offer_line), forbidden=VALUES(forbidden), dialect=VALUES(dialect)');
                $stmt->execute([
                    $id,
                    mb_substr(trim((string) ($_POST['brand_name'] ?? '')), 0, 160),
                    $logoStored,
                    $logoMime,
                    campaign_hex_color((string) ($_POST['primary_color'] ?? ''), '#0f766e'),
                    campaign_hex_color((string) ($_POST['secondary_color'] ?? ''), '#111827'),
                    $language,
                    mb_substr(trim((string) ($_POST['tone'] ?? '')), 0, 160),
                    mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 255),
                    mb_substr(trim((string) ($_POST['audience'] ?? '')), 0, 255),
                    mb_substr(trim((string) ($_POST['offer_line'] ?? '')), 0, 255),
                    mb_substr(trim((string) ($_POST['forbidden'] ?? '')), 0, 500),
                    $dialect,
                ]);
                $notice = 'brand_saved';
            } catch (RuntimeException $error) {
                $notice = 'brand_' . $error->getMessage();
            }
        }
    } elseif (!admin_table_has($pdo, 'users', 'disabled_at') || !admin_table_has($pdo, 'admin_audit_log', 'id') || !admin_table_has($pdo, 'admin_credit_adjustments', 'id')) {
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
admin_customers_nav_toolbar($adminActor);
admin_toolbar([
    ['href' => '/admin/users.php', 'label' => admin_t('All customers', 'كل العملاء'), 'class' => 'quiet'],
    ['href' => '/admin/finance-customer.php?id=' . $id, 'label' => admin_t('Finance', 'المالية'), 'class' => 'quiet'],
]);
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

$brand = admin_table_has($pdo, 'brand_kits', 'user_id') ? brand_for_user($id) : brand_empty_kit();
$canManageBrand = admin_actor_has($adminActor, 'customers.manage');
admin_panel_open(admin_t('Brand identity', 'هوية العلامة'), admin_t('Staff-only. Used as optional context for this customer’s campaigns.', 'للطاقم فقط. تُستخدم كسياق اختياري لحملات هذا العميل.'));
if ($canManageBrand) {
    echo '<form class="stack" method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
    echo '<input type="hidden" name="user_id" value="' . (int) $id . '">';
    echo '<input type="hidden" name="action" value="brand">';
    echo '<label>' . admin_h(admin_t('Brand name', 'اسم العلامة')) . '</label><input name="brand_name" value="' . admin_h((string) $brand['brand_name']) . '">';
    echo '<label>' . admin_h(admin_t('Tone of voice', 'نبرة الخطاب')) . '</label><input name="tone" value="' . admin_h((string) $brand['tone']) . '">';
    echo '<label>' . admin_h(admin_t('Audience', 'الجمهور')) . '</label><input name="audience" value="' . admin_h((string) ($brand['audience'] ?? '')) . '">';
    echo '<label>' . admin_h(admin_t('Current offer', 'العرض الحالي')) . '</label><input name="offer_line" value="' . admin_h((string) ($brand['offer_line'] ?? '')) . '">';
    echo '<label>' . admin_h(admin_t('Do not mention', 'ممنوع ذكره')) . '</label><input name="forbidden" value="' . admin_h((string) ($brand['forbidden'] ?? '')) . '">';
    echo '<label>' . admin_h(admin_t('Dialect', 'اللهجة')) . '</label><select name="dialect">';
    foreach (['' => admin_t('Unspecified', 'بدون تحديد'), 'najdi' => admin_t('Najdi', 'نجدية'), 'hijazi' => admin_t('Hijazi', 'حجازية'), 'khaleeji' => admin_t('Khaleeji', 'خليجية'), 'fusha' => admin_t('Ad Fusha', 'فصحى إعلانية')] as $code => $label) {
        $sel = (($brand['dialect'] ?? '') === $code) ? ' selected' : '';
        echo '<option value="' . admin_h((string) $code) . '"' . $sel . '>' . admin_h($label) . '</option>';
    }
    echo '</select>';
    echo '<label>' . admin_h(admin_t('Contact', 'بيانات التواصل')) . '</label><input name="contact" value="' . admin_h((string) $brand['contact']) . '">';
    echo '<label>' . admin_h(admin_t('Preferred language', 'اللغة المفضلة')) . '</label><select name="preferred_language">';
    echo '<option value="ar"' . ((($brand['preferred_language'] ?? '') === 'ar') ? ' selected' : '') . '>العربية</option>';
    echo '<option value="en"' . ((($brand['preferred_language'] ?? '') === 'en') ? ' selected' : '') . '>English</option>';
    echo '</select>';
    echo '<label>' . admin_h(admin_t('Primary color', 'اللون الأساسي')) . '</label><input name="primary_color" value="' . admin_h((string) $brand['primary_color']) . '">';
    echo '<label>' . admin_h(admin_t('Secondary color', 'اللون الثانوي')) . '</label><input name="secondary_color" value="' . admin_h((string) $brand['secondary_color']) . '">';
    echo '<label>' . admin_h(admin_t('Logo', 'الشعار')) . '</label><input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">';
    if (!empty($brand['logo_stored'])) {
        echo '<p class="note">' . admin_h(admin_t('A logo is already stored for this customer.', 'يوجد شعار محفوظ لهذا العميل.')) . '</p>';
    }
    echo '<button type="submit">' . admin_h(admin_t('Save brand identity', 'حفظ هوية العلامة')) . '</button></form>';
} else {
    echo '<p class="note">' . admin_h((string) ($brand['brand_name'] !== '' ? $brand['brand_name'] : admin_t('No brand kit yet.', 'لا توجد هوية بعد.'))) . '</p>';
}
admin_panel_close();

$campaigns = $pdo->prepare('SELECT id, title, status, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$campaigns->execute([$id]);
admin_panel_open(admin_t('Campaigns', 'الحملات'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Title', 'العنوان')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Created', 'التاريخ')) . '</th></tr>';
foreach ($campaigns as $row) {
    $st = (string) $row['status'];
    echo '<tr><td><a href="/admin/campaign.php?id=' . (int) $row['id'] . '">' . admin_h((string) $row['title']) . '</a></td><td><span class="pill' . admin_pill_for_status($st) . '">' . admin_h(admin_campaign_status_label($st)) . '</span></td><td>' . admin_h((string) $row['created_at']) . '</td></tr>';
}
admin_table_close();
admin_panel_close();

$usage = $pdo->prepare('SELECT created_at, operation, status, units, credits_deducted, campaign_id FROM usage_events WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$usage->execute([$id]);
admin_panel_open(admin_t('Usage and credit events', 'الاستخدام وحركات النقاط'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Operation', 'العملية')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Credits recorded', 'النقاط المسجّلة')) . '</th><th>' . admin_h(admin_t('Campaign', 'الحملة')) . '</th></tr>';
foreach ($usage as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . (int) $row['credits_deducted'] . '</td><td>' . admin_h((string) ($row['campaign_id'] ?? '')) . '</td></tr>';
}
admin_table_close();
admin_panel_close();

$pays = $pdo->prepare('SELECT created_at, moyasar_id, package_type, amount FROM payment_receipts WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$pays->execute([$id]);
admin_panel_open(admin_t('Payments', 'المدفوعات'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th></tr>';
foreach ($pays as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['moyasar_id']) . '</td><td>' . admin_h((string) $row['package_type']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td><td><span class="pill ok">paid</span></td></tr>';
}
admin_table_close();
admin_panel_close();

$subs = $pdo->prepare("SELECT s.status, s.period_start, s.period_end, p.code FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? ORDER BY s.id DESC LIMIT 50");
$subs->execute([$id]);
admin_panel_open(admin_t('Subscriptions', 'الاشتراكات'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Plan', 'الباقة')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Start', 'البداية')) . '</th><th>' . admin_h(admin_t('End', 'النهاية')) . '</th></tr>';
foreach ($subs as $row) {
    $st = (string) $row['status'];
    echo '<tr><td>' . admin_h((string) $row['code']) . '</td><td><span class="pill' . admin_pill_for_status($st) . '">' . admin_h(admin_subscription_status_label($st)) . '</span></td><td>' . admin_h((string) $row['period_start']) . '</td><td>' . admin_h((string) $row['period_end']) . '</td></tr>';
}
admin_table_close();
admin_panel_close();

if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
    $auditRows = admin_audit_rows_for_target($pdo, 'user', $id, 50);
    $missingStaff = admin_t('Staff (removed)', 'موظف (محذوف)');
    admin_panel_open(admin_t('Admin audit', 'سجل الإدارة'));
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Actor', 'الفاعل')) . '</th><th>' . admin_h(admin_t('Action', 'الإجراء')) . '</th><th>' . admin_h(admin_t('Reason', 'السبب')) . '</th></tr>';
    foreach ($auditRows as $row) {
        $actorLabel = admin_audit_actor_display($row, $missingStaff);
        echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h($actorLabel) . '</td><td>' . admin_h((string) $row['action']) . '</td><td>' . admin_h((string) $row['reason']) . '</td></tr>';
    }
    admin_table_close();
    admin_panel_close();
}
admin_end();
