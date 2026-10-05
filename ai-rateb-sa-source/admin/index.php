<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$subscriptions = (int) $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn();
$campaigns = (int) $pdo->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
$images = (int) $pdo->query("SELECT COALESCE(SUM(units),0) FROM usage_events WHERE operation = 'image' AND status = 'completed' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$videos = (int) $pdo->query("SELECT COALESCE(SUM(units),0) FROM usage_events WHERE operation = 'video' AND status = 'completed' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$generations = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE status = 'completed' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$failed = (int) $pdo->query("SELECT COUNT(*) FROM usage_events WHERE status = 'failed' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

$title = $adminLang === 'ar' ? 'لوحة المالك' : 'Owner dashboard';
admin_start($title);
echo '<table><tr><th>' . admin_h($adminLang === 'ar' ? 'البند' : 'Item') . '</th><th>' . admin_h($adminLang === 'ar' ? 'العدد' : 'Count') . '</th></tr>';
$rows = [
    $adminLang === 'ar' ? 'المستخدمون' : 'Users' => $users,
    $adminLang === 'ar' ? 'الاشتراكات النشطة' : 'Active subscriptions' => $subscriptions,
    $adminLang === 'ar' ? 'الحملات' : 'Campaigns' => $campaigns,
    $adminLang === 'ar' ? 'صور هذا الشهر' : 'Images this month' => $images,
    $adminLang === 'ar' ? 'فيديو هذا الشهر' : 'Videos this month' => $videos,
    $adminLang === 'ar' ? 'عمليات التوليد هذا الشهر' : 'Generations this month' => $generations,
    $adminLang === 'ar' ? 'الأخطاء هذا الشهر' : 'Failures this month' => $failed,
];
foreach ($rows as $label => $count) {
    echo '<tr><td>' . admin_h((string) $label) . '</td><td>' . (int) $count . '</td></tr>';
}
echo '</table>';
if ($adminLang === 'ar') {
    echo '<p>لا توجد مدفوعات مسجلة بعد. الأسعار المعروضة في الباقات هي أسعار الاشتراك، وليست إيراداً محصلاً.</p>';
} else {
    echo '<p>No payments are recorded yet. Plan prices are subscription prices, not collected revenue.</p>';
}
admin_end();
