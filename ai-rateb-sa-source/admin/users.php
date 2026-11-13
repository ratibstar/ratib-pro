<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('customers.view');

$q = trim((string) ($_GET['q'] ?? ''));
$page = admin_page();
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
    $like = admin_like(mb_substr($q, 0, 120));
    $params = [$like, $like];
}
$count = $pdo->prepare('SELECT COUNT(*) FROM users u ' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();
$login = admin_table_has($pdo, 'users', 'last_login_at') ? 'u.last_login_at' : 'NULL AS last_login_at';
$disabled = admin_table_has($pdo, 'users', 'disabled_at') ? 'u.disabled_at' : 'NULL AS disabled_at';
$paidExpr = admin_table_has($pdo, 'payment_receipts', 'amount')
    ? '(SELECT COALESCE(SUM(amount),0) FROM payment_receipts pr WHERE pr.user_id = u.id) AS paid_halalas'
    : '0 AS paid_halalas';
$sql = "SELECT u.id, u.name, u.email, u.role, u.package_type, u.credits_balance, u.created_at, $login, $disabled, $paidExpr, p.code AS plan_code,
    (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) AS campaigns,
    (SELECT COALESCE(SUM(units),0) FROM usage_events e WHERE e.user_id = u.id AND e.status = 'completed') AS used_units
    FROM users u
    LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
    LEFT JOIN plans p ON p.id = s.plan_id
    $where ORDER BY u.id DESC LIMIT " . admin_per_page() . ' OFFSET ' . admin_offset($page);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

admin_start(admin_t('Users', 'المستخدمون'));
admin_toolbar([
    ['href' => '/admin/finance.php?tab=customers', 'label' => admin_t('Customer finances', 'مالية العملاء'), 'class' => 'quiet'],
    ['href' => '/admin/hr-overview.php', 'label' => admin_t('Customer HR', 'موارد بشرية العملاء'), 'class' => 'quiet'],
]);
admin_panel_open(admin_t('Customer accounts', 'حسابات العملاء'), admin_t('Customers register on the site. Open a row to manage credits, disable, or view payments.', 'العملاء يسجلون من الموقع. افتح الصف لإدارة النقاط أو التعطيل أو المدفوعات.'));
echo '<form class="search" method="get"><input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Name or email', 'الاسم أو البريد')) . '"><button type="submit">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';
$canManage = admin_actor_has($adminActor, 'customers.manage');
$canFinance = admin_actor_has($adminActor, 'finance.view');
echo '<table><tr><th>' . admin_h(admin_t('Name', 'الاسم')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>' . admin_h(admin_t('Role', 'الدور')) . '</th><th>' . admin_h(admin_t('Package', 'الباقة')) . '</th><th>' . admin_h(admin_t('Paid (SAR)', 'المدفوع (ر.س)')) . '</th><th>' . admin_h(admin_t('Credits', 'النقاط')) . '</th><th>' . admin_h(admin_t('Campaigns', 'الحملات')) . '</th><th>' . admin_h(admin_t('Usage', 'الاستخدام')) . '</th><th>' . admin_h(admin_t('Registered', 'التسجيل')) . '</th><th>' . admin_h(admin_t('Last login', 'آخر دخول')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $state = ($row['disabled_at'] ?? null) ? ' <span class="pill bad">' . admin_h(admin_t('Disabled', 'معطّل')) . '</span>' : '';
    $paid = admin_table_has($pdo, 'payment_receipts', 'amount') ? admin_halalas_to_sar((int) ($row['paid_halalas'] ?? 0)) : '—';
    $uid = (int) $row['id'];
    echo '<tr><td><a href="/admin/user.php?id=' . $uid . '">' . admin_h((string) $row['name']) . '</a>' . $state . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['role']) . '</td><td>' . admin_h((string) (($row['plan_code'] ?? '') !== '' ? $row['plan_code'] : $row['package_type'])) . '</td><td>' . admin_h($paid) . '</td><td>' . (int) $row['credits_balance'] . '</td><td>' . (int) $row['campaigns'] . '</td><td>' . (int) $row['used_units'] . '</td><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h((string) ($row['last_login_at'] ?? admin_t('Not tracked', 'غير مسجّل'))) . '</td><td><div class="table-actions">';
    echo '<a class="btn small quiet" href="/admin/user.php?id=' . $uid . '">' . admin_h(admin_t('Manage', 'إدارة')) . '</a>';
    if ($canFinance) {
        echo '<a class="btn small quiet" href="/admin/finance-customer.php?id=' . $uid . '">' . admin_h(admin_t('Payments', 'مدفوعات')) . '</a>';
    }
    echo '</div></td></tr>';
}
echo '</table>';
admin_pager($page, $total, '/admin/users.php?' . ($q !== '' ? 'q=' . rawurlencode($q) . '&' : '') . 'page=');
admin_panel_close();
admin_end();
