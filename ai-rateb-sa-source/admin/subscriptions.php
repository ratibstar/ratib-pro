<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!admin_actor_is_owner($adminActor)) {
        http_response_code(403);
        exit('Owner only.');
    }
    $userId = (int) ($_POST['user_id'] ?? 0);
    $code = (string) ($_POST['plan_code'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if (mb_strlen($reason) < 3) {
        $notice = 'reason';
    } else {
        $plan = $pdo->prepare('SELECT id FROM plans WHERE code = ? AND is_active = 1 LIMIT 1');
        $plan->execute([$code]);
        $planId = (int) $plan->fetchColumn();
        $user = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $user->execute([$userId]);
        if ($planId > 0 && $user->fetch()) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE subscriptions SET status = 'replaced' WHERE user_id = ? AND status = 'active'")->execute([$userId]);
                $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))")->execute([$userId, $planId]);
                if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
                    admin_audit($pdo, $adminActor, 'subscription_replace', 'user', $userId, $reason, 'plan=' . $code);
                }
                $pdo->commit();
                $notice = 'applied';
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        } else {
            $notice = 'missing';
        }
    }
}

$rows = $pdo->query("SELECT s.id, u.id AS user_id, u.email, u.package_type, u.subscription_expires_at, p.code, p.price_sar, s.status, s.period_start, s.period_end
    FROM subscriptions s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN plans p ON p.id = s.plan_id
    ORDER BY s.id DESC
    LIMIT 200")->fetchAll();
$plans = $pdo->query('SELECT code, name_en, name_ar FROM plans WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
admin_start(admin_t('Subscriptions', 'الاشتراكات'));
$subExtra = [];
if (admin_actor_is_owner($adminActor)) {
    $subExtra[] = ['href' => '#replace-plan', 'label' => admin_t('Replace plan', 'استبدال باقة'), 'class' => 'primary'];
}
admin_finance_nav_toolbar($adminActor, $subExtra);
if ($notice !== '') {
    echo '<p class="flash">' . admin_h(admin_flash($notice)) . '</p>';
}
echo '<div class="note"><p>' . admin_h(admin_t('Sources of truth', 'مصادر الحقيقة')) . '</p><ul>';
foreach (admin_billing_truth() as $line) {
    echo '<li>' . admin_h($line) . '</li>';
}
echo '</ul><p>' . admin_h(admin_t('Recorded inconsistencies. This screen does not grant credits or rewrite balances.', 'تعارضات مسجّلة. هذه الشاشة لا تمنح نقاطًا ولا تعيد كتابة الأرصدة.')) . '</p><ul>';
$notes = $adminLang === 'ar'
    ? [
        'أسعار plans (79 / 199 / 399 / 799) تختلف عن أسعار Moyasar في checkout (99 و 249) لـ starter و pro فقط.',
        'الدفع يحدّث users.package_type والرصيد. هذه الشاشة تحدّث subscriptions فقط ولا تغيّر package_type ولا النقاط.',
        'users.subscription_expires_at يُقرأ ولا يُكتب في هذا التطبيق. نهاية الاشتراك الفعلية هي subscriptions.period_end.',
        'إيصال الدفع لا ينشئ صف اشتراك. الاشتراك المجاني يُنشأ من ensure_campaign_schema أو rateb_lock_plan.',
        'حدث الدفع في usage_events يسجّل credits_deducted = 0 رغم إضافة النقاط إلى الرصيد.',
    ]
    : [
        'plans prices (79 / 199 / 399 / 799) differ from Moyasar checkout amounts (99 and 249) for starter and pro only.',
        'Payment updates users.package_type and the credit balance. This screen updates subscriptions only. It does not change package_type or credits.',
        'users.subscription_expires_at is read and never written. The subscription end that this app stores is subscriptions.period_end.',
        'A payment receipt does not insert a subscription. The free subscription is created by ensure_campaign_schema or rateb_lock_plan.',
        'The payment usage event stores credits_deducted = 0 even though credits are added to the balance.',
    ];
foreach ($notes as $note) {
    echo '<li>' . admin_h($note) . '</li>';
}
echo '</ul></div>';
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>' . admin_h(admin_t('Plan', 'الباقة')) . '</th><th>package_type</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Period end', 'نهاية الفترة')) . '</th><th>subscription_expires_at</th></tr>';
foreach ($rows as $row) {
    $mismatch = (string) $row['package_type'] !== (string) $row['code'];
    echo '<tr><td><a href="/admin/user.php?id=' . (int) $row['user_id'] . '">' . admin_h((string) $row['email']) . '</a></td><td>' . admin_h((string) $row['code']) . '</td><td>' . ($mismatch ? '<span class="pill warn">' : '') . admin_h((string) $row['package_type']) . ($mismatch ? '</span>' : '') . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . admin_h((string) $row['period_end']) . '</td><td>' . admin_h((string) ($row['subscription_expires_at'] ?? '—')) . '</td></tr>';
}
admin_table_close();
echo '<h2 id="replace-plan">' . admin_h(admin_t('Replace a user plan', 'استبدال باقة مستخدم')) . '</h2>';
echo '<form method="post" class="inline"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
echo '<input name="user_id" inputmode="numeric" placeholder="' . admin_h(admin_t('User id', 'رقم المستخدم')) . '">';
echo '<select name="plan_code">';
foreach ($plans as $plan) {
    $label = $adminLang === 'ar' ? (string) $plan['name_ar'] : (string) $plan['name_en'];
    echo '<option value="' . admin_h((string) $plan['code']) . '">' . admin_h($label) . '</option>';
}
echo '</select><input name="reason" required minlength="3" maxlength="500" placeholder="' . admin_h(admin_t('Reason', 'السبب')) . '">';
echo '<button type="submit">' . admin_h(admin_t('Save', 'حفظ')) . '</button></form>';
admin_end();
