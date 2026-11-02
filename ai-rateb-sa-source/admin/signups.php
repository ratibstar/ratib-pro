<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/welcome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    rateb_welcome_release($pdo, (int) ($_POST['user_id'] ?? 0), (int) $adminUser['id']);
    header('Location: /admin/signups.php');
    exit;
}

$rows = $pdo->query('SELECT a.id, a.user_id, u.email, a.email_verified, a.decision, a.detail, a.admin_user_id, g.status AS grant_status, g.credits, a.created_at
    FROM signup_audit a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN welcome_grants g ON g.user_id = a.user_id
    ORDER BY a.id DESC
    LIMIT 200')->fetchAll();

admin_start($adminLang === 'ar' ? 'سجل التسجيل' : 'Signup audit');
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'الوقت' : 'Time') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحساب' : 'Account') . '</th><th>' . admin_h($adminLang === 'ar' ? 'البريد موثّق' : 'Email verified') . '</th><th>' . admin_h($adminLang === 'ar' ? 'القرار' : 'Decision') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الرصيد الترحيبي' : 'Welcome') . '</th><th>' . admin_h($adminLang === 'ar' ? 'التفاصيل' : 'Detail') . '</th><th>' . admin_h($adminLang === 'ar' ? 'المراجع' : 'Admin') . '</th><th></th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) ($row['email'] ?? '')) . '</td><td>' . ((int) $row['email_verified'] === 1 ? 'yes' : 'no') . '</td><td>' . admin_h((string) $row['decision']) . '</td><td>' . admin_h((string) ($row['grant_status'] ?? '')) . ' / ' . (int) $row['credits'] . '</td><td>' . admin_h((string) $row['detail']) . '</td><td>' . (int) ($row['admin_user_id'] ?? 0) . '</td><td>';
    if ((string) ($row['grant_status'] ?? '') === 'review') {
        echo '<form method="post">' . '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="user_id" value="' . (int) $row['user_id'] . '"><button type="submit">' . admin_h($adminLang === 'ar' ? 'منح بعد المراجعة' : 'Grant after review') . '</button></form>';
    }
    echo '</td></tr>';
}
echo '</table>';
admin_end();
