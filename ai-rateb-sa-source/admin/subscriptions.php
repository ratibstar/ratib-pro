<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $code = (string) ($_POST['plan_code'] ?? '');
    $plan = $pdo->prepare('SELECT id FROM plans WHERE code = ? AND is_active = 1 LIMIT 1');
    $plan->execute([$code]);
    $planId = (int) $plan->fetchColumn();
    $user = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $user->execute([$userId]);
    if ($planId > 0 && $user->fetch()) {
        $pdo->prepare("UPDATE subscriptions SET status = 'replaced' WHERE user_id = ? AND status = 'active'")->execute([$userId]);
        $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))")->execute([$userId, $planId]);
    }
    header('Location: /admin/subscriptions.php');
    exit;
}

$rows = $pdo->query("SELECT s.id, u.email, p.code, p.price_sar, s.status, s.period_start, s.period_end
    FROM subscriptions s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN plans p ON p.id = s.plan_id
    ORDER BY s.id DESC
    LIMIT 200")->fetchAll();
$plans = $pdo->query('SELECT code, name_en, name_ar FROM plans WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
admin_start($adminLang === 'ar' ? 'الاشتراكات' : 'Subscriptions');
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'البريد' : 'Email') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الباقة' : 'Plan') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحالة' : 'Status') . '</th><th>' . admin_h($adminLang === 'ar' ? 'البداية' : 'Start') . '</th><th>' . admin_h($adminLang === 'ar' ? 'النهاية' : 'End') . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['code']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . admin_h((string) $row['period_start']) . '</td><td>' . admin_h((string) $row['period_end']) . '</td></tr>';
}
echo '</table>';
echo '<h2>' . admin_h($adminLang === 'ar' ? 'تغيير باقة مستخدم' : 'Change a user plan') . '</h2>';
echo '<form method="post" class="inline"><input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
echo '<input name="user_id" inputmode="numeric" placeholder="' . admin_h($adminLang === 'ar' ? 'رقم المستخدم' : 'User id') . '">';
echo '<select name="plan_code">';
foreach ($plans as $plan) {
    $label = $adminLang === 'ar' ? (string) $plan['name_ar'] : (string) $plan['name_en'];
    echo '<option value="' . admin_h((string) $plan['code']) . '">' . admin_h($label) . '</option>';
}
echo '</select><button type="submit">' . admin_h($adminLang === 'ar' ? 'حفظ' : 'Save') . '</button></form>';
admin_end();
