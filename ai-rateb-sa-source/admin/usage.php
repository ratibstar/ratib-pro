<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$summary = $pdo->query("SELECT operation, status, COALESCE(SUM(units),0) AS units
    FROM usage_events
    WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    GROUP BY operation, status
    ORDER BY operation, status")->fetchAll();
$rows = $pdo->query("SELECT e.created_at, u.email, e.campaign_id, e.operation, e.units, e.seconds, e.status, e.cost_sar
    FROM usage_events e
    INNER JOIN users u ON u.id = e.user_id
    ORDER BY e.id DESC
    LIMIT 200")->fetchAll();

admin_start($adminLang === 'ar' ? 'الاستخدام' : 'Usage');
echo '<h2>' . admin_h($adminLang === 'ar' ? 'هذا الشهر' : 'This month') . '</h2><table><tr><th>' . admin_h($adminLang === 'ar' ? 'العملية' : 'Operation') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحالة' : 'Status') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الوحدات' : 'Units') . '</th></tr>';
foreach ($summary as $row) {
    echo '<tr><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . (int) $row['units'] . '</td></tr>';
}
echo '</table><h2>' . admin_h($adminLang === 'ar' ? 'آخر العمليات' : 'Recent events') . '</h2>';
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'التاريخ' : 'Date') . '</th><th>' . admin_h($adminLang === 'ar' ? 'المستخدم' : 'User') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحملة' : 'Campaign') . '</th><th>' . admin_h($adminLang === 'ar' ? 'العملية' : 'Operation') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحالة' : 'Status') . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) ($row['campaign_id'] ?? '')) . '</td><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td></tr>';
}
echo '</table>';
admin_end();
