<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('dashboard.view');

/** @return array{key:string,start:string,end:string,prev_start:string,prev_end:string,grain:string,slots:array<int,string>} */
function admin_dash_range(): array
{
    $key = (string) ($_GET['range'] ?? '30');
    $allowed = ['today' => 1, '7' => 7, '30' => 30, '90' => 90, 'year' => 0];
    if (!isset($allowed[$key])) {
        $key = '30';
    }
    $end = new DateTimeImmutable('tomorrow');
    if ($key === 'year') {
        $start = new DateTimeImmutable(date('Y-01-01'));
        $grain = 'month';
    } else {
        $start = (new DateTimeImmutable('today'))->modify('-' . ($allowed[$key] - 1) . ' days');
        $grain = 'day';
    }
    $seconds = $end->getTimestamp() - $start->getTimestamp();
    $prevEnd = $start;
    $prevStart = $start->modify('-' . $seconds . ' seconds');
    $slots = [];
    if ($grain === 'month') {
        $cursor = $start;
        while ($cursor < $end) {
            $slots[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('first day of next month');
        }
    } else {
        $cursor = $start;
        $today = new DateTimeImmutable('today');
        while ($cursor <= $today) {
            $slots[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
    }
    return [
        'key' => $key,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'prev_start' => $prevStart->format('Y-m-d H:i:s'),
        'prev_end' => $prevEnd->format('Y-m-d H:i:s'),
        'grain' => $grain,
        'slots' => $slots,
    ];
}

function admin_dash_count(PDO $pdo, string $sql, string ...$bounds): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bounds);
    return (int) $stmt->fetchColumn();
}

/** @param array<int,string> $slots @return array<int,array{label:string,value:int}> */
function admin_dash_series(PDO $pdo, string $sql, string $start, string $end, array $slots, string $grain): array
{
    $expr = $grain === 'month' ? "DATE_FORMAT(created_at, '%Y-%m')" : 'DATE(created_at)';
    $stmt = $pdo->prepare(sprintf($sql, $expr));
    $stmt->execute([$start, $end]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['bucket']] = (int) $row['total'];
    }
    $points = [];
    foreach ($slots as $slot) {
        $points[] = ['label' => $slot, 'value' => $map[$slot] ?? 0];
    }
    return $points;
}

/** @param array<int,array{label:string,value:int}> $points */
function admin_dash_chart(string $title, array $points): void
{
    $max = 1;
    foreach ($points as $point) {
        $max = max($max, $point['value']);
    }
    echo '<article class="chart-card"><h3>' . admin_h($title) . '</h3><div class="chart">';
    foreach ($points as $point) {
        $height = max(2, (int) round(($point['value'] / $max) * 100));
        echo '<button type="button" class="bar"><i style="height:' . $height . '%"></i><em><b>' . admin_h($point['label']) . '</b><span>' . admin_h((string) $point['value']) . '</span></em></button>';
    }
    echo '</div></article>';
}

function admin_dash_card(string $label, ?string $value): void
{
    echo '<article class="stat"><b>' . admin_h($value ?? admin_t('Unavailable', 'غير متاح')) . '</b><span>' . admin_h($label) . '</span></article>';
}

$range = admin_dash_range();
$start = $range['start'];
$end = $range['end'];
$na = null;

$users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$signups = admin_dash_count($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?', $start, $end);
$active = admin_table_has($pdo, 'users', 'last_login_at')
    ? (int) $pdo->query('SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn()
    : $na;
$campaigns = (int) $pdo->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
$monthCampaigns = (int) $pdo->query("SELECT COUNT(*) FROM campaigns WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$subs = (int) $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND period_end >= NOW()")->fetchColumn();

$collected = admin_dash_count($pdo, 'SELECT COALESCE(SUM(amount),0) FROM payment_receipts WHERE created_at >= ? AND created_at < ?', $start, $end);
$paidCount = admin_dash_count($pdo, 'SELECT COUNT(*) FROM payment_receipts WHERE created_at >= ? AND created_at < ?', $start, $end);
$previousCollected = admin_dash_count($pdo, 'SELECT COALESCE(SUM(amount),0) FROM payment_receipts WHERE created_at >= ? AND created_at < ?', $range['prev_start'], $range['prev_end']);
$failedPayments = admin_table_has($pdo, 'payment_orders', 'status')
    ? admin_dash_count($pdo, "SELECT COUNT(*) FROM payment_orders WHERE status = 'failed' AND created_at >= ? AND created_at < ?", $start, $end)
    : $na;
$pendingPayments = admin_table_has($pdo, 'payment_orders', 'status')
    ? admin_dash_count($pdo, "SELECT COUNT(*) FROM payment_orders WHERE status = 'pending' AND created_at >= ? AND created_at < ?", $start, $end)
    : $na;
$catalog = $pdo->query("SELECT COALESCE(SUM(p.price_sar),0) FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.status = 'active' AND s.period_end >= NOW() AND p.code <> 'free'")->fetchColumn();
$catalogLabel = number_format((float) $catalog, 2, '.', '');

$ops = admin_dash_count($pdo, "SELECT COUNT(*) FROM usage_events WHERE status IN ('completed','failed','reserved') AND created_at >= ? AND created_at < ?", $start, $end);
$ok = admin_dash_count($pdo, "SELECT COUNT(*) FROM usage_events WHERE status = 'completed' AND created_at >= ? AND created_at < ?", $start, $end);
$failedOps = admin_dash_count($pdo, "SELECT COUNT(*) FROM usage_events WHERE status = 'failed' AND created_at >= ? AND created_at < ?", $start, $end);
$creditStmt = $pdo->prepare('SELECT operation, COALESCE(SUM(credits_deducted),0) AS credits, COUNT(*) AS events FROM usage_events WHERE created_at >= ? AND created_at < ? GROUP BY operation');
$creditStmt->execute([$start, $end]);
$buckets = admin_credit_buckets($creditStmt->fetchAll());
$remaining = (int) $pdo->query('SELECT COALESCE(SUM(credits_balance),0) FROM users')->fetchColumn();
$serviceStmt = $pdo->prepare("SELECT operation, COUNT(*) AS events FROM usage_events WHERE status IN ('completed','failed') AND created_at >= ? AND created_at < ? GROUP BY operation ORDER BY events DESC LIMIT 12");
$serviceStmt->execute([$start, $end]);
$services = $serviceStmt->fetchAll();

$signupSeries = admin_dash_series($pdo, 'SELECT %s AS bucket, COUNT(*) AS total FROM users WHERE created_at >= ? AND created_at < ? GROUP BY bucket', $start, $end, $range['slots'], $range['grain']);
$campaignSeries = admin_dash_series($pdo, 'SELECT %s AS bucket, COUNT(*) AS total FROM campaigns WHERE created_at >= ? AND created_at < ? GROUP BY bucket', $start, $end, $range['slots'], $range['grain']);
$revenueSeries = admin_dash_series($pdo, 'SELECT %s AS bucket, COALESCE(SUM(amount),0) AS total FROM payment_receipts WHERE created_at >= ? AND created_at < ? GROUP BY bucket', $start, $end, $range['slots'], $range['grain']);
$activitySeries = admin_dash_series($pdo, "SELECT %s AS bucket, COUNT(*) AS total FROM usage_events WHERE status = 'completed' AND created_at >= ? AND created_at < ? GROUP BY bucket", $start, $end, $range['slots'], $range['grain']);
$newUsers = admin_dash_count($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?', $start, $end);
$converted = admin_dash_count($pdo, 'SELECT COUNT(DISTINCT r.user_id) FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id WHERE u.created_at >= ? AND u.created_at < ? AND r.created_at >= ? AND r.created_at < ?', $start, $end, $start, $end);
if ($newUsers > 0) {
    $conversion = (string) (int) round(($converted / $newUsers) * 100) . '%';
} else {
    $conversion = null;
}

$failByType = $pdo->prepare("SELECT operation, COUNT(*) AS events FROM usage_events WHERE status = 'failed' AND created_at >= ? AND created_at < ? GROUP BY operation ORDER BY events DESC LIMIT 8");
$failByType->execute([$start, $end]);
$failRows = $failByType->fetchAll();
$recentFails = $pdo->prepare("SELECT created_at, operation, status FROM usage_events WHERE status = 'failed' AND created_at >= ? AND created_at < ? ORDER BY id DESC LIMIT 8");
$recentFails->execute([$start, $end]);
$recentFailRows = $recentFails->fetchAll();
$auditRows = [];
$auditReady = admin_table_has($pdo, 'admin_audit_log', 'id');
if ($auditReady) {
    $audit = $pdo->prepare('SELECT created_at, action, target_type FROM admin_audit_log WHERE created_at >= ? AND created_at < ? ORDER BY id DESC LIMIT 8');
    $audit->execute([$start, $end]);
    $auditRows = $audit->fetchAll();
}
$providers = admin_provider_snapshot();

$delta = $collected - $previousCollected;
$deltaText = ($delta > 0 ? '+' : '') . admin_halalas_to_sar($delta);

$filters = [
    'today' => admin_t('Today', 'اليوم'),
    '7' => admin_t('7 days', '٧ أيام'),
    '30' => admin_t('30 days', '٣٠ يومًا'),
    '90' => admin_t('90 days', '٩٠ يومًا'),
    'year' => admin_t('This year', 'هذه السنة'),
];

admin_start(admin_t('Dashboard', 'اللوحة'));
admin_toolbar([
    ['href' => '/admin/users.php', 'label' => admin_t('Customers', 'العملاء'), 'class' => 'primary'],
    ['href' => '/admin/finance.php', 'label' => admin_t('Finance', 'المالية')],
    ['href' => '/admin/campaigns.php', 'label' => admin_t('Campaigns', 'الحملات')],
    ['href' => '/admin/settings.php', 'label' => admin_t('System', 'النظام')],
]);
echo '<div class="filters">';
foreach ($filters as $filterKey => $filterLabel) {
    $class = $filterKey === $range['key'] ? ' class="on"' : '';
    echo '<a' . $class . ' href="/admin/?range=' . admin_h((string) $filterKey) . '">' . admin_h($filterLabel) . '</a>';
}
echo '</div>';

echo '<h2 class="section-title">' . admin_h(admin_t('Business overview', 'نظرة الأعمال')) . '</h2><section class="stats">';
admin_dash_card(admin_t('Total users', 'إجمالي المستخدمين'), (string) $users);
admin_dash_card(admin_t('New registrations', 'تسجيلات جديدة'), (string) $signups);
admin_dash_card(admin_t('Active users in 30 days', 'مستخدمون نشطون خلال ٣٠ يومًا'), $active === null ? null : (string) $active);
admin_dash_card(admin_t('Total campaigns', 'إجمالي الحملات'), (string) $campaigns);
admin_dash_card(admin_t('Campaigns this month', 'حملات هذا الشهر'), (string) $monthCampaigns);
admin_dash_card(admin_t('Active subscriptions', 'اشتراكات نشطة'), (string) $subs);
echo '</section>';

echo '<h2 class="section-title">' . admin_h(admin_t('Financial performance', 'الأداء المالي')) . '</h2><section class="stats">';
admin_dash_card(admin_t('Collected revenue (SAR)', 'الإيراد المحصّل (ر.س)'), admin_halalas_to_sar($collected));
admin_dash_card(admin_t('Successful payments', 'مدفوعات ناجحة'), (string) $paidCount);
admin_dash_card(admin_t('Failed payments', 'مدفوعات فاشلة'), $failedPayments === null ? null : (string) $failedPayments);
admin_dash_card(admin_t('Pending payments', 'مدفوعات معلّقة'), $pendingPayments === null ? null : (string) $pendingPayments);
admin_dash_card(admin_t('Catalog value of active paid plans (SAR)', 'قيمة الباقات المدفوعة النشطة (ر.س)'), $catalogLabel);
admin_dash_card(admin_t('Change versus the previous period (SAR)', 'التغير عن الفترة السابقة (ر.س)'), $deltaText);
admin_dash_card(admin_t('Refunded revenue', 'إيراد مسترد'), null);
echo '</section>';
echo '<div class="charts">';
admin_dash_chart(admin_t('Collected revenue', 'الإيراد المحصّل'), $revenueSeries);
echo '</div>';
echo '<p class="note">' . admin_h(admin_t(
    'Collected revenue counts each paid receipt once. Pending and failed orders are excluded. Refunds are not stored, so refunded revenue is unavailable.',
    'الإيراد المحصّل يحسب كل إيصال مدفوع مرة واحدة. الطلبات المعلّقة والفاشلة غير داخلة. الاسترداد غير مخزّن، لذلك الإيراد المسترد غير متاح.'
)) . '</p>';

echo '<h2 class="section-title">' . admin_h(admin_t('AI platform usage', 'استخدام المنصة')) . '</h2><section class="stats">';
admin_dash_card(admin_t('AI operations', 'عمليات الذكاء'), (string) $ops);
admin_dash_card(admin_t('Successful operations', 'عمليات ناجحة'), (string) $ok);
admin_dash_card(admin_t('Failed operations', 'عمليات فاشلة'), (string) $failedOps);
admin_dash_card(admin_t('Consumed credits', 'نقاط مستهلكة'), (string) $buckets['consumed']);
admin_dash_card(admin_t('Remaining credits now', 'النقاط المتبقية الآن'), (string) $remaining);
echo '</section>';
echo '<article class="card"><h2>' . admin_h(admin_t('Usage by service', 'الاستخدام حسب الخدمة')) . '</h2>';
if ($services === []) {
    echo '<p class="note">' . admin_h(admin_t('No operations in this period.', 'لا توجد عمليات في هذه الفترة.')) . '</p>';
} else {
    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Service', 'الخدمة')) . '</th><th>' . admin_h(admin_t('Operations', 'العمليات')) . '</th></tr>';
    foreach ($services as $service) {
        echo '<tr><td>' . admin_h((string) $service['operation']) . '</td><td>' . admin_h((string) $service['events']) . '</td></tr>';
    }
    admin_table_close();
}
echo '<p class="note">' . admin_h(admin_t(
    'Operations are not stored with a provider name, so a provider breakdown is unavailable.',
    'العمليات غير مخزّنة باسم المزوّد، لذلك توزيع المزوّدين غير متاح.'
)) . '</p></article>';

echo '<h2 class="section-title">' . admin_h(admin_t('Growth', 'النمو')) . '</h2><section class="stats">';
admin_dash_card(admin_t('Share of new accounts with a collected payment', 'نسبة الحسابات الجديدة التي لديها دفعة محصّلة'), $conversion);
echo '</section><div class="charts">';
admin_dash_chart(admin_t('Registrations', 'التسجيلات'), $signupSeries);
admin_dash_chart(admin_t('New campaigns', 'الحملات الجديدة'), $campaignSeries);
admin_dash_chart(admin_t('Completed operations', 'العمليات المكتملة'), $activitySeries);
echo '</div>';

echo '<h2 class="section-title">' . admin_h(admin_t('Platform health', 'صحة المنصة')) . '</h2>';
echo '<article class="card"><h2>' . admin_h(admin_t('Provider configuration', 'إعداد المزوّدين')) . '</h2>';
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Provider', 'المزوّد')) . '</th><th>' . admin_h(admin_t('Configuration', 'الإعداد')) . '</th><th>' . admin_h(admin_t('Live status', 'الحالة المباشرة')) . '</th></tr>';
foreach ($providers as $provider) {
    $configured = !empty($provider['configured']) ? admin_t('Configured', 'مُعد') : admin_t('Not configured', 'غير مُعد');
    echo '<tr><td>' . admin_h((string) $provider['label']) . '</td><td>' . admin_h($configured) . '</td><td>' . admin_h(admin_t('Unavailable', 'غير متاح')) . '</td></tr>';
}
admin_table_close();
echo '<p class="note">' . admin_h(admin_t(
    'Configuration only shows whether a key is present. The application does not probe provider uptime.',
    'الإعداد يوضح وجود المفتاح فقط. التطبيق لا يفحص جاهزية المزوّد.'
)) . '</p></article>';

echo '<div class="split">';
echo '<article class="card"><h2>' . admin_h(admin_t('Failed operations', 'العمليات الفاشلة')) . '</h2>';
if ($failRows === []) {
    echo '<p class="note">' . admin_h(admin_t('No failed operations in this period.', 'لا توجد عمليات فاشلة في هذه الفترة.')) . '</p>';
} else {
    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Service', 'الخدمة')) . '</th><th>' . admin_h(admin_t('Count', 'العدد')) . '</th></tr>';
    foreach ($failRows as $row) {
        echo '<tr><td>' . admin_h((string) $row['operation']) . '</td><td>' . admin_h((string) $row['events']) . '</td></tr>';
    }
    admin_table_close();
}
echo '</article>';
echo '<article class="card"><h2>' . admin_h(admin_t('Latest failed operations', 'آخر العمليات الفاشلة')) . '</h2>';
if ($recentFailRows === []) {
    echo '<p class="note">' . admin_h(admin_t('No failed operations in this period.', 'لا توجد عمليات فاشلة في هذه الفترة.')) . '</p>';
} else {
    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('When', 'الوقت')) . '</th><th>' . admin_h(admin_t('Service', 'الخدمة')) . '</th></tr>';
    foreach ($recentFailRows as $row) {
        echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['operation']) . '</td></tr>';
    }
    admin_table_close();
}
echo '<p class="note">' . admin_h(admin_t('Stored rows have no error message.', 'الصفوف المخزّنة بلا نص للخطأ.')) . '</p></article>';
echo '<article class="card"><h2>' . admin_h(admin_t('Latest audit events', 'آخر أحداث التدقيق')) . '</h2>';
if (!$auditReady) {
    echo '<p class="note">' . admin_h(admin_t('Unavailable', 'غير متاح')) . '</p>';
} elseif ($auditRows === []) {
    echo '<p class="note">' . admin_h(admin_t('No audit events in this period.', 'لا توجد أحداث تدقيق في هذه الفترة.')) . '</p>';
} else {
    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('When', 'الوقت')) . '</th><th>' . admin_h(admin_t('Action', 'الإجراء')) . '</th></tr>';
    foreach ($auditRows as $row) {
        echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) $row['action']) . '</td></tr>';
    }
    admin_table_close();
}
echo '</article></div>';
admin_end();
