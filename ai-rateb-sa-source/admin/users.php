<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$rows = $pdo->query("SELECT u.id, u.name, u.email, u.role, u.created_at, p.code AS plan_code,
    (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) AS campaigns,
    (SELECT COALESCE(SUM(units),0) FROM usage_events e WHERE e.user_id = u.id AND e.status = 'completed') AS used_units
    FROM users u
    LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
    LEFT JOIN plans p ON p.id = s.plan_id
    ORDER BY u.id DESC
    LIMIT 200")->fetchAll();

admin_start($adminLang === 'ar' ? 'المستخدمون' : 'Users');
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'الاسم' : 'Name') . '</th><th>' . admin_h($adminLang === 'ar' ? 'البريد' : 'Email') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحالة' : 'Role') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الباقة' : 'Plan') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحملات' : 'Campaigns') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الاستهلاك' : 'Usage') . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['name']) . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['role']) . '</td><td>' . admin_h((string) ($row['plan_code'] ?? '')) . '</td><td>' . (int) $row['campaigns'] . '</td><td>' . (int) $row['used_units'] . '</td></tr>';
}
echo '</table>';
admin_end();
