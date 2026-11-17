<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/ai/provider-health.php';

admin_require_permission('operations.view');

$summary = $pdo->query("SELECT operation, status, COUNT(*) AS events, COALESCE(SUM(units),0) AS units,
    COALESCE(SUM(CASE WHEN credits_deducted > 0 THEN credits_deducted ELSE 0 END),0) AS credits,
    COALESCE(SUM(cost_sar),0) AS cost_sum, SUM(cost_sar IS NOT NULL) AS cost_rows
    FROM usage_events GROUP BY operation, status ORDER BY operation, status")->fetchAll();
$errors = $pdo->query("SELECT error_code, COUNT(*) AS events FROM video_jobs WHERE error_code <> '' GROUP BY error_code ORDER BY events DESC LIMIT 30")->fetchAll();
$recent = $pdo->query("SELECT e.created_at, u.email, e.campaign_id, e.operation, e.units, e.status, e.credits_deducted, e.cost_sar
    FROM usage_events e INNER JOIN users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 100")->fetchAll();
$costKnown = (int) $pdo->query('SELECT COUNT(*) FROM usage_events WHERE cost_sar IS NOT NULL')->fetchColumn();

$bucketRows = $pdo->query('SELECT operation, COALESCE(SUM(credits_deducted),0) AS credits, COUNT(*) AS events FROM usage_events GROUP BY operation')->fetchAll();
$buckets = admin_credit_buckets($bucketRows);
admin_start(admin_t('AI operations', 'عمليات الذكاء'));
admin_toolbar([
    ['href' => '/admin/users.php', 'label' => admin_t('Customers', 'العملاء'), 'class' => 'quiet'],
]);
echo '<section class="stats">';
foreach ([
    [admin_t('Consumed', 'مستهلك'), $buckets['consumed']],
    [admin_t('Refunded', 'مسترد'), $buckets['refunded']],
    [admin_t('Granted by payment', 'ممنوح بالدفع'), $buckets['granted']],
    [admin_t('Added manually', 'مضاف يدويًا'), $buckets['adjusted_add']],
    [admin_t('Deducted manually', 'مخصوم يدويًا'), $buckets['adjusted_deduct']],
] as [$label, $value]) {
    echo '<article class="stat"><b>' . (int) $value . '</b><span>' . admin_h($label) . '</span></article>';
}
echo '</section>';
echo '<h2 id="providers">' . admin_h(admin_t('Provider configuration', 'إعداد المزوّدين')) . '</h2>';
echo '<p class="note">' . admin_h(admin_t(
    'Configured means a non-empty secret or URL is present on this server. It does not mean the provider answered successfully.',
    '«مُعد» يعني أن سرًا أو رابطًا غير فارغ موجود على الخادم. لا يعني أن المزوّد استجاب بنجاح.'
)) . '</p>';
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Provider', 'المزوّد')) . '</th><th>' . admin_h(admin_t('Secret present', 'السر موجود')) . '</th><th>' . admin_h(admin_t('Selected route', 'المسار المختار')) . '</th><th>' . admin_h(admin_t('Note', 'ملاحظة')) . '</th></tr>';
foreach (admin_provider_snapshot() as $row) {
    echo '<tr><td>' . admin_h((string) $row['label']) . '</td><td><span class="pill ' . ($row['configured'] ? 'ok' : 'warn') . '">' . admin_h($row['configured'] ? admin_t('Present', 'موجود') : admin_t('Missing', 'غير موجود')) . '</span></td><td>' . admin_h($row['selected'] ? admin_t('Yes', 'نعم') : admin_t('No', 'لا')) . '</td><td>' . admin_h((string) $row['note']) . '</td></tr>';
}
admin_table_close();

$usage24 = rateb_health_usage_24h($pdo);
$videoHealth = rateb_health_video_summary($pdo);
$wanUrl = trim((string) getenv('RATEB_PRIVATE_VIDEO_URL'));
$wanTok = trim((string) getenv('RATEB_PRIVATE_VIDEO_TOKEN'));
$wanProbe = rateb_health_probe_wan($wanUrl, $wanTok);

admin_panel_open(admin_t('Live signals (24h)', 'إشارات حيّة (٢٤ ساعة)'), admin_t(
    'Recent successful usage rows and optional Wan /health probe. Not a SLA dashboard.',
    'آخر عمليات ناجحة + فحص Wan اختياري. ليس لوحة اتفاقية مستوى خدمة.'
));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Signal', 'إشارة')) . '</th><th>' . admin_h(admin_t('Last success', 'آخر نجاح')) . '</th><th>' . admin_h(admin_t('Count 24h', 'عدد ٢٤ س')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th></tr>';
foreach ($usage24 as $row) {
    $ok = (int) $row['count_24h'] > 0;
    echo '<tr><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) ($row['last_at'] ?? '—')) . '</td><td>' . (int) $row['count_24h'] . '</td>';
    echo '<td><span class="pill ' . ($ok ? 'ok' : 'warn') . '">' . admin_h($ok ? admin_t('Active', 'نشط') : admin_t('Quiet', 'هادئ')) . '</span></td></tr>';
}
echo '<tr><td>Wan /health</td><td>—</td><td>—</td><td><span class="pill ' . ($wanProbe['ok'] ? 'ok' : 'warn') . '">' . admin_h($wanProbe['label']) . '</span> ' . admin_h($wanProbe['detail']) . '</td></tr>';
if ($videoHealth['last_completed']) {
    echo '<tr><td>video_jobs completed</td><td>' . admin_h((string) $videoHealth['last_completed']) . '</td><td>—</td><td><span class="pill ok">ok</span></td></tr>';
}
if ($videoHealth['last_error_code']) {
    echo '<tr><td>video last error</td><td>' . admin_h((string) $videoHealth['last_error_at']) . '</td><td>—</td><td><span class="pill bad">' . admin_h((string) $videoHealth['last_error_code']) . '</span></td></tr>';
}
admin_table_close();
admin_panel_close();

admin_panel_open(admin_t('Operations', 'العمليات'), admin_t('The credits column below is the raw database sum.', 'عمود النقاط أدناه هو المجموع الخام من قاعدة البيانات.'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Operation', 'العملية')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Events', 'العدد')) . '</th><th>' . admin_h(admin_t('Units', 'الوحدات')) . '</th><th>' . admin_h(admin_t('Raw credits column', 'عمود النقاط الخام')) . '</th></tr>';
foreach ($summary as $row) {
    echo '<tr><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . (int) $row['events'] . '</td><td>' . (int) $row['units'] . '</td><td>' . (int) $row['credits'] . '</td></tr>';
}
admin_table_close();
admin_panel_close();
echo '<h2>' . admin_h(admin_t('Video error codes', 'أكواد أخطاء الفيديو')) . '</h2>';
if ($errors === []) {
    echo '<p class="note">' . admin_h(admin_t('No stored video error codes.', 'لا توجد أكواد خطأ مخزّنة للفيديو.')) . '</p>';
} else {
    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Code', 'الرمز')) . '</th><th>' . admin_h(admin_t('Events', 'العدد')) . '</th></tr>';
    foreach ($errors as $row) {
        echo '<tr><td>' . admin_h((string) $row['error_code']) . '</td><td>' . (int) $row['events'] . '</td></tr>';
    }
    admin_table_close();
}
echo '<h2>' . admin_h(admin_t('Cost', 'التكلفة')) . '</h2><p class="note">' . admin_h($costKnown > 0
    ? admin_t('cost_sar has stored values. Sum them only from the column, not from provider invoices.', 'يوجد قيم في cost_sar. اجمعها من العمود فقط.')
    : admin_t('cost_sar is null for every usage row. No verified provider cost is available.', 'cost_sar فارغ في كل صفوف الاستخدام. لا توجد تكلفة موثّقة.')) . '</p>';
admin_panel_open(admin_t('Recent events', 'آخر العمليات'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('User', 'المستخدم')) . '</th><th>' . admin_h(admin_t('Campaign', 'الحملة')) . '</th><th>' . admin_h(admin_t('Operation', 'العملية')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Credits', 'النقاط')) . '</th></tr>';
foreach ($recent as $row) {
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) ($row['campaign_id'] ?? '')) . '</td><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['status']) . '</td><td>' . (int) $row['credits_deducted'] . '</td></tr>';
}
admin_table_close();
admin_panel_close();
admin_end();
