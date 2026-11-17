<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/company_hr_schema.php';

admin_require_permission('customers.view');

ensure_company_hr_schema($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
    $like = admin_like(mb_substr($q, 0, 120));
    $params = [$like, $like];
}
$sql = "SELECT u.id, u.name, u.email, u.created_at,
    (SELECT COUNT(*) FROM hr_employees e WHERE e.company_user_id = u.id) AS employees,
    (SELECT COUNT(*) FROM hr_employees e WHERE e.company_user_id = u.id AND e.status = 'active') AS active_employees
    FROM users u $where ORDER BY employees DESC, u.id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

admin_start(admin_t('Customer HR (read-only)', 'موارد بشرية العملاء (قراءة فقط)'));
admin_toolbar([
    ['href' => '/admin', 'label' => admin_t('Dashboard', 'لوحة التحليلات'), 'class' => 'quiet'],
    ['href' => '/admin/users.php', 'label' => admin_t('Customer accounts', 'حسابات العملاء'), 'class' => 'quiet'],
    ['href' => '/admin/finance.php?tab=customers', 'label' => admin_t('Customer finances', 'مالية العملاء'), 'class' => 'quiet'],
    ['href' => admin_public_site_url(), 'label' => admin_t('Public site', 'الموقع العام'), 'class' => 'quiet', 'attrs' => ['target' => '_blank', 'rel' => 'noopener']],
    ['href' => '/hr', 'label' => admin_t('Customer HR on site', 'HR على الموقع'), 'class' => 'primary', 'attrs' => ['target' => '_blank', 'rel' => 'noopener']],
]);
echo '<p class="note">' . admin_h(admin_t(
    'This page is monitoring only. To add or delete employees, the customer logs into the site and opens /hr (Human resources in the campaigns menu).',
    'هذه الصفحة للمراقبة فقط. الإضافة والحذف تتم عندما يسجّل العميل دخوله ويفتح /hr من قائمة الحملات (الموارد البشرية).'
)) . '</p>';
admin_panel_open(admin_t('HR usage by customer', 'استخدام HR لكل عميل'));
echo '<form class="search" method="get"><input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Name or email', 'الاسم أو البريد')) . '"><button type="submit">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('Customer', 'العميل')) . '</th><th>' . admin_h(admin_t('Employees', 'الموظفون')) . '</th><th>' . admin_h(admin_t('Active', 'نشط')) . '</th><th>' . admin_h(admin_t('Registered', 'التسجيل')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . admin_h((string) $row['name']) . '<br><span class="note">' . admin_h((string) $row['email']) . '</span></td>';
    echo '<td>' . (int) $row['employees'] . '</td><td>' . (int) $row['active_employees'] . '</td><td>' . admin_h((string) $row['created_at']) . '</td>';
    $uid = (int) $row['id'];
    $editHref = '/admin/hr-customer.php?id=' . $uid;
    echo '<td>';
    admin_crud_row_actions($editHref, '/admin/user.php?id=' . $uid, false, $uid);
    echo '</td></tr>';
}
if ($rows === []) {
    admin_table_empty_row(5, admin_t('No customers.', 'لا عملاء.'));
}
admin_table_close();
admin_panel_close();
admin_end();
