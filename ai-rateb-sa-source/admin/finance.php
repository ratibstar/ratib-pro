<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('finance.view');

$range = admin_finance_range();
$start = $range['start'];
$end = $range['end'];
$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'customers', 'payments'], true)) {
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

admin_start(admin_t('Financial management', 'الإدارة المالية'));

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
] as $tabKey => $tabLabel) {
    $on = $tab === $tabKey ? ' on' : '';
    $href = '/admin/finance.php' . admin_finance_query_string(['tab' => $tabKey, 'page' => '']);
    echo '<a class="finance-tab' . $on . '" href="' . admin_h($href) . '">' . admin_h($tabLabel) . '</a>';
}
echo '</nav>';

$exportBase = '/admin/finance-export.php' . admin_finance_query_string(['page' => '']);

if ($tab === 'overview') {
    echo '<section class="stats">';
    echo '<article class="stat"><b>' . admin_h(admin_halalas_to_sar($collected)) . ' SAR</b><span>' . admin_h(admin_t('Collected payments', 'مدفوعات محصّلة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $paidCount . '</b><span>' . admin_h(admin_t('Successful receipts', 'إيصالات ناجحة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $pendingCount . ' · ' . admin_h(admin_halalas_to_sar($pendingHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Pending orders', 'طلبات معلّقة')) . '</span></article>';
    echo '<article class="stat"><b>' . (int) $failedCount . ' · ' . admin_h(admin_halalas_to_sar($failedHalalas)) . ' SAR</b><span>' . admin_h(admin_t('Failed orders', 'طلبات فاشلة')) . '</span></article>';
    echo '<article class="stat"><b>' . admin_h(admin_t('Unavailable', 'غير متاح')) . '</b><span>' . admin_h(admin_t('Refunded amount', 'مبلغ مسترد')) . '</span></article>';
    echo '<article class="stat"><b>' . admin_h(admin_t('Unavailable', 'غير متاح')) . '</b><span>' . admin_h(admin_t('Net revenue', 'صافي الإيراد')) . '</span></article>';
    echo '</section>';

    echo '<p class="note">' . admin_h(admin_t(
        'Collected amounts come only from payment_receipts (unique moyasar_id). Pending and failed rows use payment_orders and are not revenue. Currency is SAR; amounts are stored in halalas. Refunds are not stored, so refunded and net figures stay unavailable.',
        'المبالغ المحصّلة من payment_receipts فقط (moyasar_id فريد). المعلّق والفاشل من payment_orders وليست إيرادًا. العملة ريال والمخزن بالهللة. الاسترداد غير مخزّن، لذا المسترد والصافي غير متاحين.'
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
    echo '<script>document.querySelectorAll(".chart .bar").forEach(function(bar){bar.addEventListener("click",function(){var on=bar.classList.contains("is-tip");document.querySelectorAll(".chart .bar.is-tip").forEach(function(node){node.classList.remove("is-tip");});if(!on){bar.classList.add("is-tip");}});});</script>';
}

if ($tab === 'customers') {
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

    echo '<table><tr><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Payments', 'المدفوعات')) . '</th><th>' . admin_h(admin_t('Subscription', 'الاشتراك')) . '</th><th>' . admin_h(admin_t('Credits', 'النقاط')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Last payment', 'آخر دفع')) . '</th></tr>';
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
        echo '<td>' . admin_h((string) ($row['last_payment'] ?? '—')) . '</td></tr>';
    }
    echo '</table>';
    $custBase = '/admin/finance.php' . admin_finance_query_string(['tab' => 'customers']);
    admin_pager($page, $total, $custBase . (str_contains($custBase, '?') ? '&' : '?') . 'page=');
}

if ($tab === 'payments') {
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

    echo '<table><tr><th>' . admin_h(admin_t('Reference', 'المرجع')) . '</th><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>SAR</th><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Moyasar ID', 'معرّف Moyasar')) . '</th></tr>';
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
        echo '<td><span class="pill ' . $pill . '">' . admin_h((string) $row['status']) . '</span></td>';
        echo '<td>' . admin_h((string) $row['moyasar_ref']) . '</td></tr>';
    }
    echo '</table>';
    $pageBase = '/admin/finance.php' . admin_finance_query_string(['tab' => 'payments', 'page' => '']);
    $sep = str_contains($pageBase, '?') ? '&' : '?';
    admin_pager($page, $total, $pageBase . $sep . 'page=');
}

admin_end();
