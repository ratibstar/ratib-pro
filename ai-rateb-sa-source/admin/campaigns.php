<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$rows = $pdo->query("SELECT c.id, c.title, c.status, c.created_at, u.email, b.campaign_language, b.resolved_language,
    (SELECT COUNT(*) FROM campaign_outputs o WHERE o.campaign_id = c.id) AS outputs
    FROM campaigns c
    INNER JOIN users u ON u.id = c.user_id
    LEFT JOIN campaign_briefs b ON b.campaign_id = c.id
    ORDER BY c.id DESC
    LIMIT 200")->fetchAll();

admin_start($adminLang === 'ar' ? 'الحملات' : 'Campaigns');
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'العنوان' : 'Title') . '</th><th>' . admin_h($adminLang === 'ar' ? 'المستخدم' : 'User') . '</th><th>' . admin_h($adminLang === 'ar' ? 'الحالة' : 'Status') . '</th><th>' . admin_h($adminLang === 'ar' ? 'لغة الحملة' : 'Campaign language') . '</th><th>' . admin_h($adminLang === 'ar' ? 'المخرجات' : 'Outputs') . '</th><th>' . admin_h($adminLang === 'ar' ? 'التاريخ' : 'Created') . '</th></tr>';
foreach ($rows as $row) {
    $language = (string) ($row['campaign_language'] ?? '');
    if ((string) ($row['resolved_language'] ?? '') !== '') {
        $language .= ' → ' . (string) $row['resolved_language'];
    }
    echo '<tr><td>' . admin_h((string) $row['title']) . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . admin_h($language) . '</td><td>' . (int) $row['outputs'] . '</td><td>' . admin_h((string) $row['created_at']) . '</td></tr>';
}
echo '</table>';
admin_end();
