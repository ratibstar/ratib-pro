<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/payment/commerce.php';

admin_require_permission('finance.view');

$range = admin_finance_range();
$start = $range['start'];
$end = $range['end'];
$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'customers', 'payments', 'subscriptions', 'reconcile'], true)) {
    $tab = 'overview';
}
$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
$page = admin_page();

$hasOrders = admin_table_has($pdo, 'payment_orders', 'status');
$disabledCol = admin_table_has($pdo, 'users', 'disabled_at');

$receiptStmt = $pdo->prepare('SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_receipts WHERE created_at >= ? AND created_at < ?');
$receiptStmt->execute([$start, $end]);
$receiptAgg = $receiptStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
$paidCount = (int) $receiptAgg['rows_count'];
$collected = (int) $receiptAgg['halalas'];

$pendingCount = 0;
$pendingHalalas = 0;
$failedCount = 0;
$failedHalalas = 0;
if ($hasOrders) {
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_orders WHERE status = 'pending' AND created_at >= ? AND created_at < ?");
    $pendingStmt->execute([$start, $end]);
    $pendingAgg = $pendingStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
    $pendingCount = (int) $pendingAgg['rows_count'];
    $pendingHalalas = (int) $pendingAgg['halalas'];
    $failedStmt = $pdo->prepare("SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_orders WHERE status = 'failed' AND created_at >= ? AND created_at < ?");
    $failedStmt->execute([$start, $end]);
    $failedAgg = $failedStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
    $failedCount = (int) $failedAgg['rows_count'];
    $failedHalalas = (int) $failedAgg['halalas'];
}

$dailySeries = admin_finance_series($pdo, 'DATE(created_at)', $start, $end);
$monthlySeries = admin_finance_series($pdo, "DATE_FORMAT(created_at, '%Y-%m')", $start, $end);
$yearlySeries = admin_finance_series($pdo, "DATE_FORMAT(created_at, '%Y')", $start, $end);

$filterLinks = [
    'today' => admin_t('Today', 'اليوم'),
    '7' => admin_t('7 days', '٧ أيام'),
    '30' => admin_t('30 days', '٣٠ يومًا'),
    '90' => admin_t('90 days', '٩٠ يومًا'),
    'year' => admin_t('This year', 'هذه السنة'),
];

$mrrCatalog = (float) $pdo->query("SELECT COALESCE(SUM(p.price_sar),0) FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.status = 'active' AND s.period_end >= NOW() AND p.code <> 'free'")->fetchColumn();
$activeSubs = (int) $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND period_end >= NOW()")->fetchColumn();
$refundedHalalas = rateb_refunds_sum_halalas($pdo, $start, $end);
$netHalalas = max(0, $collected - $refundedHalalas);
$stalePending = 0;
if ($hasOrders) {
    $stalePending = (int) $pdo->query("SELECT COUNT(*) FROM payment_orders WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
}

admin_start(admin_t('Financial management', 'الإدارة المالية'));
$financeToolbar = [
    ['href' => '/admin/payments.php', 'label' => admin_t('All receipts', 'كل الإيصالات')],
    ['href' => '/admin/subscriptions.php', 'label' => admin_t('Manage subscriptions', 'إدارة الاشتراكات')],
    ['href' => '/admin/plans.php', 'label' => admin_t('Plans', 'الباقات')],
    ['href' => '/admin/refunds.php', 'label' => admin_t('Refunds', 'الاستردادات')],
];
if (admin_actor_has($adminActor, 'finance.export')) {
    $rangeKey = (string) ($range['key'] ?? '30');
    $financeToolbar[] = ['href' => '/admin/finance-export.php?export=summary&range=' . rawurlencode($rangeKey), 'label' => admin_t('Export CSV', 'تصدير CSV')];
    if ($hasOrders) {
        $financeToolbar[] = ['href' => '/admin/finance-export.php?export=pending&range=' . rawurlencode($rangeKey), 'label' => admin_t('Export pending', 'تصدير المعلّقة')];
    }
}
admin_crud_toolbar([
    'screen' => 'finance',
    'can_manage' => admin_actor_is_owner($adminActor),
    'add_href' => '/admin/refunds.php#record-refund',
    'links' => $financeToolbar,
]);

echo '<div class="filters">';
foreach ($filterLinks as $filterKey => $filterLabel) {
    $class = $range['key'] === $filterKey ? ' class="on"' : '';
    $href = '/admin/finance.php?range=' . rawurlencode((string) $filterKey) . '&tab=' . rawurlencode($tab);
    if ($q !== '') {
        $href .= '&q=' . rawurlencode($q);
    }
    if ($status !== 'all') {
        $href .= '&status=' . rawurlencode($status);
    }
    echo '<a' . $class . ' href="' . admin_h($href) . '">' . admin_h($filterLabel) . '</a>';
}
echo '</div>';

echo '<form class="search finance-custom" method="get">';
echo '<input type="hidden" name="range" value="custom">';
echo '<input type="hidden" name="tab" value="' . admin_h($tab) . '">';
echo '<label>' . admin_h(admin_t('From', 'من')) . ' <input type="date" name="from" value="' . admin_h($range['from']) . '"></label>';
echo '<label>' . admin_h(admin_t('To', 'إلى')) . ' <input type="date" name="to" value="' . admin_h($range['to']) . '"></label>';
echo '<button type="submit">' . admin_h(admin_t('Apply range', 'تطبيق الفترة')) . '</button>';
echo '</form>';

echo '<nav class="finance-tabs">';
foreach ([
    'overview' => admin_t('Overview', 'نظرة عامة'),
    'customers' => admin_t('Customer finances', 'مالية العملاء'),
    'payments' => admin_t('Payment history', 'سجل المدفوعات'),
    'subscriptions' => admin_t('Subscriptions', 'الاشتراكات'),
    'reconcile' => admin_t('Reconciliation', 'المطابقة'),
] as $tabKey => $tabLabel) {
    if ($tabKey === 'reconcile' && !$hasOrders) {
        continue;
    }
    $on = $tab === $tabKey ? ' on' : '';
    $href = '/admin/finance.php' . admin_finance_query_string(['tab' => $tabKey, 'page' => '']);
    echo '<a class="finance-tab' . $on . '" href="' . admin_h($href) . '">' . admin_h($tabLabel) . '</a>';
}
echo '</nav>';

$exportBase = '/admin/finance-export.php' . admin_finance_query_string(['page' => '']);

if ($tab === 'overview') {
    admin_panel_open(admin_t('Finance snapshot', 'ملخص المالية'), admin_t('Collected cash from receipts in the selected date range.', 'النقد المحصّل من الإيصالات ضمن الفترة المحددة.'));
    echo '<section class="stats">';
    echo '<article class="stat"><b>' . admin_h(admin_halalas_to_sar($collected)) . ' SAR</b><span>' . admin_h(admin_t('Collected payments', 'مدفوعات محصّلة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $paidCount . '</b><span>' . admin_h(admin_t('Successful receipts', 'إيصالات ناجحة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $pendingCount . ' · ' . admin_h(admin_halalas_to_sar($pendingHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Pending orders', 'طلبات معلّقة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $failedCount . ' · ' . admin_h(admin_halalas_to_sar($failedHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Failed orders', 'طلبات فاشلة')) . '</span></article>';
    echo '<article class="stat"><b>' . admin_h(admin_halalas_to_sar($refundedHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Refunded amount', 'مبلغ مسترد')) . '</span></article>';
    echo '<article class="stat"><b>' . admin_h(admin_halalas_to_sar($netHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Net revenue', 'صافي الإيراد')) . '</span></article>';
    if ($stalePending > 0) {
        echo '<article class="stat"><b>' . (int) $stalePending . '</b><span>' . admin_h(admin_t('Pending &gt; 24h (review)', 'معلّق &gt; ٢٤ ساعة')) . '</span></article>';
    }
    echo '<article class="stat"><b>' . admin_h(number_format($mrrCatalog, 2, '.', '')) . ' SAR</b><span>' . admin_h(admin_t('Catalog MRR (active paid plans)', 'MRR كتالوج (باقات مدفوعة نشطة)')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $activeSubs . '</b><span>' . admin_h(admin_t('Active subscriptions', 'اشتراكات نشطة')) . '</span></article>';
    echo '</section>';
    admin_panel_close();

    echo '<p class="note">' . admin_h(admin_t(
        'Net revenue is collected minus refunds recorded in payment_refunds. Stale pending orders older than 24 hours should be checked in Moyasar.',
        'صافي الإيراد = المحصّل ناقص الاستردادات في payment_refunds. راجع الطلبات المعلّقة أكثر من ٢٤ ساعة في Moyasar.'
    )) . '</p>';

    echo '<p class="export-links"><a class="btn" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=summary') . '">' . admin_h(admin_t('Export summary CSV', 'تصدير ملخص CSV')) . '</a> ';
    echo '<a class="btn quiet" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=receipts') . '">' . admin_h(admin_t('Export receipts', 'تصدير الإيصالات')) . '</a> ';
    echo '<a class="btn quiet" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=pending') . '">' . admin_h(admin_t('Export pending', 'تصدير المعلّق')) . '</a> ';
    echo '<a class="btn quiet" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=failed') . '">' . admin_h(admin_t('Export failed', 'تصدير الفاشل')) . '</a></p>';

    echo '<div class="charts">';
    admin_finance_chart(admin_t('Daily collected (SAR)', 'محصّل يومي (ر.س)'), $dailySeries);
    admin_finance_chart(admin_t('Monthly collected (SAR)', 'محصّل شهري (ر.س)'), $monthlySeries);
    admin_finance_chart(admin_t('Yearly collected (SAR)', 'محصّل سنوي (ر.س)'), $yearlySeries);
    echo '</div>';
}

if ($tab === 'customers') {
    admin_toolbar([
        ['href' => '/admin', 'label' => admin_t('Dashboard', 'لوحة التحليلات'), 'class' => 'quiet'],
        ['href' => '/admin/users.php', 'label' => admin_t('Customer accounts', 'حسابات العملاء'), 'class' => 'quiet'],
        ['href' => '/admin/hr-overview.php', 'label' => admin_t('Customer HR', 'موارد بشرية العملاء'), 'class' => 'quiet'],
        ['href' => admin_public_site_url(), 'label' => admin_t('Public site', 'الموقع العام'), 'class' => 'quiet', 'attrs' => ['target' => '_blank', 'rel' => 'noopener']],
    ]);
    admin_panel_open(admin_t('Customer finances', 'مالية العملاء'), admin_t(
        'Totals per customer account. Customers pay and subscribe on the public site.',
        'مجاميع لكل حساب عميل. الدفع والاشتراك يتم على الموقع العام.'
    ));
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = "WHERE (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
        $like = admin_like(mb_substr($q, 0, 120));
        $params = [$like, $like];
    }
    $countSql = 'SELECT COUNT(*) FROM users u ' . $where;
    $count = $pdo->prepare($countSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $disabledExpr = $disabledCol ? 'u.disabled_at' : 'NULL AS disabled_at';
    $sql = "SELECT u.id, u.name, u.email, u.credits_balance, $disabledExpr,
        COALESCE(pay.paid_halalas, 0) AS paid_halalas,
        COALESCE(pay.paid_count, 0) AS paid_count,
        pay.last_payment,
        pl.code AS plan_code
        FROM users u
        LEFT JOIN (
            SELECT user_id, SUM(amount) AS paid_halalas, COUNT(*) AS paid_count, MAX(created_at) AS last_payment
            FROM payment_receipts GROUP BY user_id
        ) pay ON pay.user_id = u.id
        LEFT JOIN subscriptions s ON s.id = (
            SELECT s2.id FROM subscriptions s2
            WHERE s2.user_id = u.id AND s2.status = 'active' AND s2.period_end >= NOW()
            ORDER BY s2.id DESC LIMIT 1
        )
        LEFT JOIN plans pl ON pl.id = s.plan_id
        $where
        ORDER BY pay.paid_halalas DESC, u.id DESC
        LIMIT " . admin_per_page() . ' OFFSET ' . admin_offset($page);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo '<form class="search" method="get">';
    echo '<input type="hidden" name="tab" value="customers">';
    echo '<input type="hidden" name="range" value="' . admin_h($range['key']) . '">';
    if ($range['from'] !== '') {
        echo '<input type="hidden" name="from" value="' . admin_h($range['from']) . '"><input type="hidden" name="to" value="' . admin_h($range['to']) . '">';
    }
    echo '<input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Name or email', 'الاسم أو البريد')) . '"><button type="submit">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';

    echo '<p class="export-links"><a class="btn" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=customers') . '">' . admin_h(admin_t('Export customer summaries', 'تصدير ملخص العملاء')) . '</a></p>';
    echo '<p class="note">' . admin_h(admin_t(
        'Paid totals include successful receipts only. Subscription names are catalog status, not collected cash.',
        'إجمالي المدفوع يشمل الإيصالات الناجحة فقط. أسماء الاشتراك حالة كتالوج وليست نقدًا محصّلًا.'
    )) . '</p>';

    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Payments', 'المدفوعات')) . '</th><th>' . admin_h(admin_t('Subscription', 'الاشتراك')) . '</th><th>' . admin_h(admin_t('Credits', 'النقاط')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Last payment', 'آخر دفع')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
    foreach ($rows as $row) {
        $disabled = ($row['disabled_at'] ?? null) !== null && (string) $row['disabled_at'] !== '';
        $plan = (string) ($row['plan_code'] ?? '');
        echo '<tr><td><a href="/admin/finance-customer.php?id=' . (int) $row['id'] . '">' . admin_h((string) $row['name']) . '</a></td>';
        echo '<td>' . admin_h((string) $row['email']) . '</td>';
        echo '<td>' . admin_h(admin_halalas_to_sar((int) $row['paid_halalas'])) . '</td>';
        echo '<td>' . (int) $row['paid_count'] . '</td>';
        echo '<td>' . admin_h($plan !== '' ? $plan : '—') . '</td>';
        echo '<td>' . (int) $row['credits_balance'] . '</td>';
        echo '<td><span class="pill ' . ($disabled ? 'bad' : 'ok') . '">' . admin_h($disabled ? admin_t('Disabled', 'معطّل') : admin_t('Active', 'نشط')) . '</span></td>';
        echo '<td>' . admin_h((string) ($row['last_payment'] ?? '—')) . '</td><td><div class="table-actions">';
        echo '<a class="btn small quiet" href="/admin/finance-customer.php?id=' . (int) $row['id'] . '">' . admin_h(admin_t('Finance', 'مالية')) . '</a>';
        echo '<a class="btn small quiet" href="/admin/user.php?id=' . (int) $row['id'] . '">' . admin_h(admin_t('Account', 'حساب')) . '</a></div></td></tr>';
    }
    admin_table_close();
    $custBase = '/admin/finance.php' . admin_finance_query_string(['tab' => 'customers']);
    admin_pager($page, $total, $custBase . (str_contains($custBase, '?') ? '&' : '?') . 'page=');
    admin_panel_close();
}

if ($tab === 'payments') {
    admin_panel_open(admin_t('Payment history', 'سجل المدفوعات'));
  if (!in_array($status, ['all', 'paid', 'pending', 'failed'], true)) {
        $status = 'all';
    }

    $like = $q !== '' ? admin_like(mb_substr($q, 0, 120)) : '';
    $parts = [];
    $params = [];

    if ($status === 'all' || $status === 'paid') {
        $receiptWhere = 'r.created_at >= ? AND r.created_at < ?';
        $receiptParams = [$start, $end];
        if ($q !== '') {
            $receiptWhere .= " AND (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR r.moyasar_id LIKE ? ESCAPE '\\\\')";
            array_push($receiptParams, $like, $like, $like);
        }
        $parts[] = "SELECT r.id AS row_id, 'receipt' AS kind, r.created_at, r.moyasar_id AS moyasar_ref, r.package_type AS package_code, r.amount, 'paid' AS status, u.id AS user_id, u.name, u.email
            FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id WHERE $receiptWhere";
        $params = array_merge($params, $receiptParams);
    }

    if ($hasOrders && ($status === 'all' || $status === 'pending' || $status === 'failed')) {
        $orderStatuses = $status === 'all' ? ['pending', 'failed'] : [$status];
        foreach ($orderStatuses as $orderStatus) {
            $orderWhere = 'o.created_at >= ? AND o.created_at < ? AND o.status = ?';
            $orderParams = [$start, $end, $orderStatus];
            if ($q !== '') {
                $orderWhere .= " AND (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR COALESCE(o.moyasar_invoice_id,'') LIKE ? ESCAPE '\\\\')";
                array_push($orderParams, $like, $like, $like);
            }
            $parts[] = "SELECT o.id AS row_id, 'order' AS kind, o.created_at, COALESCE(o.moyasar_invoice_id, '') AS moyasar_ref, o.package_code, o.amount, o.status, u.id AS user_id, u.name, u.email
                FROM payment_orders o INNER JOIN users u ON u.id = o.user_id WHERE $orderWhere";
            $params = array_merge($params, $orderParams);
        }
    }

    $rows = [];
    $total = 0;
    if ($parts !== []) {
        $union = implode(' UNION ALL ', $parts);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM (' . $union . ') AS finance_rows');
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $listStmt = $pdo->prepare($union . ' ORDER BY created_at DESC LIMIT ' . admin_per_page() . ' OFFSET ' . admin_offset($page));
        $listStmt->execute($params);
        $rows = $listStmt->fetchAll();
    }

    echo '<form class="search" method="get">';
    echo '<input type="hidden" name="tab" value="payments">';
    echo '<input type="hidden" name="range" value="' . admin_h($range['key']) . '">';
    if ($range['from'] !== '') {
        echo '<input type="hidden" name="from" value="' . admin_h($range['from']) . '"><input type="hidden" name="to" value="' . admin_h($range['to']) . '">';
    }
    echo '<input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Customer, email, or reference', 'العميل أو البريد أو المرجع')) . '">';
    echo '<select name="status">';
    foreach ([
        'all' => admin_t('All statuses', 'كل الحالات'),
        'paid' => admin_t('Paid', 'مدفوع'),
        'pending' => admin_t('Pending', 'معلّق'),
        'failed' => admin_t('Failed', 'فاشل'),
    ] as $code => $label) {
        echo '<option value="' . admin_h($code) . '"' . ($status === $code ? ' selected' : '') . '>' . admin_h($label) . '</option>';
    }
    echo '</select><button type="submit">' . admin_h(admin_t('Filter', 'تصفية')) . '</button></form>';

    echo '<p class="export-links"><a class="btn" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=receipts') . '">' . admin_h(admin_t('Export receipts', 'تصدير الإيصالات')) . '</a>';
    if ($hasOrders) {
        echo ' <a class="btn quiet" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=pending') . '">' . admin_h(admin_t('Export pending', 'تصدير المعلّق')) . '</a>';
        echo ' <a class="btn quiet" href="' . admin_h($exportBase . (str_contains($exportBase, '?') ? '&' : '?') . 'export=failed') . '">' . admin_h(admin_t('Export failed', 'تصدير الفاشل')) . '</a>';
    }
    echo '</p>';

    admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Moyasar ID', 'معرّف Moyasar')) . '</th></tr>';
    foreach ($rows as $row) {
        $ref = ($row['kind'] === 'receipt' ? 'R' : 'O') . (int) $row['row_id'];
        $pill = match ((string) $row['status']) {
            'paid' => 'ok',
            'pending' => 'warn',
            default => 'bad',
        };
        echo '<tr><td>' . admin_h($ref) . '</td>';
        echo '<td><a href="/admin/finance-customer.php?id=' . (int) $row['user_id'] . '">' . admin_h((string) $row['name']) . '</a></td>';
        echo '<td>' . admin_h((string) $row['email']) . '</td>';
        echo '<td>' . admin_h((string) $row['package_code']) . '</td>';
        echo '<td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td>';
        echo '<td>' . admin_h((string) $row['created_at']) . '</td>';
        $pst = (string) $row['status'];
        echo '<td><span class="pill ' . $pill . '">' . admin_h(admin_payment_status_label($pst)) . '</span></td>';
        echo '<td>' . admin_h((string) $row['moyasar_ref']) . '</td></tr>';
    }
    admin_table_close();
    $pageBase = '/admin/finance.php' . admin_finance_query_string(['tab' => 'payments', 'page' => '']);
    $sep = str_contains($pageBase, '?') ? '&' : '?';
    admin_pager($page, $total, $pageBase . $sep . 'page=');
    admin_panel_close();
}

if ($tab === 'subscriptions') {
    $subRows = $pdo->query("SELECT s.id, u.id AS user_id, u.email, u.package_type, p.code, p.price_sar, s.status, s.period_end
        FROM subscriptions s
        INNER JOIN users u ON u.id = s.user_id
        INNER JOIN plans p ON p.id = s.plan_id
        ORDER BY s.id DESC LIMIT 200")->fetchAll();
    admin_panel_open(admin_t('Subscription ledger', 'سجل الاشتراكات'), admin_t('Catalog subscriptions table. To replace a plan (owner), use the dedicated subscriptions page.', 'جدول الاشتراكات. لاستبدال باقة (مالك) استخدم صفحة الاشتراكات المخصصة.'));
    echo '<p class="page-toolbar"><a class="btn primary" href="/admin/subscriptions.php">' . admin_h(admin_t('Open full subscription control', 'فتح التحكم الكامل بالاشتراكات')) . '</a></p>';
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Plan', 'الباقة')) . '</th><th>' . admin_h(admin_t('Catalog SAR', 'كتالوج ر.س')) . '</th><th>' . admin_h(admin_t('package_type', 'package_type')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Period end', 'نهاية الفترة')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
    foreach ($subRows as $row) {
        $st = (string) $row['status'];
        $mismatch = (string) $row['package_type'] !== (string) $row['code'];
        echo '<tr><td><a href="/admin/finance-customer.php?id=' . (int) $row['user_id'] . '">' . admin_h((string) $row['email']) . '</a></td>';
        echo '<td>' . admin_h((string) $row['code']) . '</td><td>' . admin_h((string) $row['price_sar']) . '</td>';
        echo '<td>' . ($mismatch ? '<span class="pill warn">' : '') . admin_h((string) $row['package_type']) . ($mismatch ? '</span>' : '') . '</td>';
        echo '<td><span class="pill' . admin_pill_for_status($st) . '">' . admin_h(admin_subscription_status_label($st)) . '</span></td>';
        echo '<td>' . admin_h((string) $row['period_end']) . '</td><td><div class="table-actions"><a class="btn small quiet" href="/admin/user.php?id=' . (int) $row['user_id'] . '">' . admin_h(admin_t('Account', 'حساب')) . '</a></div></td></tr>';
    }
    if ($subRows === []) {
        admin_table_empty_row(7, admin_t('No subscriptions.', 'لا اشتراكات.'));
    }
    admin_table_close();
    admin_panel_close();
}

if ($tab === 'reconcile' && $hasOrders) {
    $reconcileRows = $pdo->query("SELECT o.id AS order_id, o.user_id, u.name, u.email, o.package_code, o.amount, o.status, o.moyasar_invoice_id, o.created_at,
        (SELECT r.id FROM payment_receipts r WHERE r.user_id = o.user_id AND (
            (o.moyasar_invoice_id IS NOT NULL AND o.moyasar_invoice_id <> '' AND r.moyasar_id = o.moyasar_invoice_id)
            OR (r.amount = o.amount AND r.package_type = o.package_code AND r.created_at >= o.created_at)
        ) ORDER BY r.id DESC LIMIT 1) AS receipt_id
        FROM payment_orders o
        INNER JOIN users u ON u.id = o.user_id
        WHERE o.status IN ('pending','failed')
        ORDER BY o.id DESC LIMIT 250")->fetchAll();
    admin_panel_open(admin_t('Moyasar reconciliation', 'مطابقة Moyasar'), admin_t(
        'Pending/failed orders with optional matching receipt. Receipt rows remain the revenue source of truth.',
        'طلبات معلّقة/فاشلة مع إيصال مطابق إن وُجد. الإيصالات هي مصدر الإيراد.'
    ));
    if ($stalePending > 0) {
        echo '<p class="flash warn">' . admin_h(admin_t(
            (string) $stalePending . ' pending orders are older than 24 hours — review in Moyasar.',
            (string) $stalePending . ' طلبًا معلّقًا منذ أكثر من ٢٤ ساعة — راجعها في Moyasar.'
        )) . '</p>';
    }
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Order', 'طلب')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Moyasar invoice', 'فاتورة')) . '</th><th>' . admin_h(admin_t('Receipt match', 'إيصال مطابق')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
    foreach ($reconcileRows as $row) {
        $st = (string) $row['status'];
        $pill = $st === 'pending' ? 'warn' : 'bad';
        $match = $row['receipt_id'] !== null ? '<span class="pill ok">R' . (int) $row['receipt_id'] . '</span>' : '<span class="pill bad">' . admin_h(admin_t('None', 'لا يوجد')) . '</span>';
        echo '<tr><td>O' . (int) $row['order_id'] . '</td>';
        echo '<td><a href="/admin/finance-customer.php?id=' . (int) $row['user_id'] . '">' . admin_h((string) $row['name']) . '</a><br><span class="note">' . admin_h((string) $row['email']) . '</span></td>';
        echo '<td>' . admin_h((string) $row['package_code']) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['amount'])) . '</td>';
        echo '<td><span class="pill ' . $pill . '">' . admin_h(admin_payment_status_label($st)) . '</span></td>';
        echo '<td>' . admin_h((string) ($row['moyasar_invoice_id'] ?? '')) . '</td><td>' . $match . '</td>';
        echo '<td><div class="table-actions"><a class="btn small quiet" href="/admin/user.php?id=' . (int) $row['user_id'] . '">' . admin_h(admin_t('Account', 'حساب')) . '</a></div></td></tr>';
    }
    if ($reconcileRows === []) {
        admin_table_empty_row(8, admin_t('No pending or failed orders.', 'لا طلبات معلّقة أو فاشلة.'));
    }
    admin_table_close();
    echo '<p class="note">' . admin_h(admin_t(
        'There is no automatic refund UI in this release. Investigate unmatched pending rows in Moyasar, then rely on webhook + receipts for collected cash.',
        'لا واجهة استرداد تلقائي في هذا الإصدار. راجع الطلبات المعلّقة غير المطابقة في Moyasar؛ الإيراد المعتمد هو الإيصالات بعد الويب هوك.'
    )) . '</p>';
    admin_panel_close();
}

admin_end();
