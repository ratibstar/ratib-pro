<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/company_hr_schema.php';

admin_require_permission('customers.view');

ensure_company_hr_schema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$userStmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$id]);
$user = $userStmt->fetch();

$tab = (string) ($_GET['tab'] ?? 'employees');
if (!in_array($tab, ['employees', 'departments', 'leave', 'payroll', 'attendance'], true)) {
    $tab = 'employees';
}

admin_start(admin_t('Customer HR detail', 'تفاصيل موارد بشرية العميل'));
admin_toolbar([
    ['href' => '/admin/hr-overview.php', 'label' => admin_t('HR overview', 'نظرة HR'), 'class' => 'quiet'],
    ['href' => '/admin/user.php?id=' . $id, 'label' => admin_t('Account', 'الحساب'), 'class' => 'quiet'],
    ['href' => '/admin/finance-customer.php?id=' . $id, 'label' => admin_t('Finances', 'المالية'), 'class' => 'quiet'],
]);

if (!$user) {
    echo '<p class="note">' . admin_h(admin_t('Customer not found.', 'العميل غير موجود.')) . '</p>';
    admin_end();
    exit;
}

$counts = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM hr_employees WHERE company_user_id = ?) AS employees,
    (SELECT COUNT(*) FROM hr_employees WHERE company_user_id = ? AND status = 'active') AS active,
    (SELECT COUNT(*) FROM hr_departments WHERE company_user_id = ?) AS departments,
    (SELECT COUNT(*) FROM hr_leave_requests WHERE company_user_id = ? AND status = 'pending') AS leave_pending,
    (SELECT COUNT(*) FROM hr_payroll_records WHERE company_user_id = ?) AS payroll_rows");
$counts->execute([$id, $id, $id, $id, $id]);
$stat = $counts->fetch() ?: [];

admin_panel_open((string) $user['name'], (string) $user['email']);
echo '<section class="stats">';
echo '<article class="stat"><b>' . (int) ($stat['employees'] ?? 0) . '</b><span>' . admin_h(admin_t('Employees', 'الموظفون')) . '</span></article>';
echo '<article class="stat"><b>' . (int) ($stat['active'] ?? 0) . '</b><span>' . admin_h(admin_t('Active', 'نشط')) . '</span></article>';
echo '<article class="stat"><b>' . (int) ($stat['departments'] ?? 0) . '</b><span>' . admin_h(admin_t('Departments', 'الأقسام')) . '</span></article>';
echo '<article class="stat"><b>' . (int) ($stat['leave_pending'] ?? 0) . '</b><span>' . admin_h(admin_t('Pending leave', 'إجازات معلّقة')) . '</span></article>';
echo '</section>';
echo '<p class="note">' . admin_h(admin_t(
    'Read-only support view. The customer manages records at /hr after logging in.',
    'عرض دعم للقراءة فقط. العميل يدير السجلات من /hr بعد تسجيل الدخول.'
)) . '</p>';
admin_panel_close();

echo '<nav class="finance-tabs">';
foreach ([
    'employees' => admin_t('Employees', 'الموظفون'),
    'departments' => admin_t('Departments', 'الأقسام'),
    'attendance' => admin_t('Attendance', 'الحضور'),
    'leave' => admin_t('Leave', 'الإجازات'),
    'payroll' => admin_t('Payroll', 'الرواتب'),
] as $tKey => $tLabel) {
    $on = $tab === $tKey ? ' on' : '';
    echo '<a class="finance-tab' . $on . '" href="/admin/hr-customer.php?id=' . $id . '&tab=' . rawurlencode($tKey) . '">' . admin_h($tLabel) . '</a>';
}
echo '</nav>';

if ($tab === 'employees') {
    admin_panel_open(admin_t('Employees', 'الموظفون'));
    $rows = $pdo->prepare("SELECT e.id, e.full_name, e.email, e.job_title, e.status, e.hired_at, d.name AS dept
        FROM hr_employees e
        LEFT JOIN hr_departments d ON d.id = e.department_id AND d.company_user_id = e.company_user_id
        WHERE e.company_user_id = ? ORDER BY e.full_name LIMIT 300");
    $rows->execute([$id]);
    $list = $rows->fetchAll();
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Name', 'الاسم')) . '</th><th>' . admin_h(admin_t('Department', 'القسم')) . '</th><th>' . admin_h(admin_t('Job', 'المسمى')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Hired', 'التعيين')) . '</th></tr>';
    foreach ($list as $row) {
        echo '<tr><td>' . admin_h((string) $row['full_name']) . '<br><span class="note">' . admin_h((string) ($row['email'] ?? '')) . '</span></td>';
        echo '<td>' . admin_h((string) ($row['dept'] ?? '—')) . '</td><td>' . admin_h((string) ($row['job_title'] ?? '')) . '</td>';
        echo '<td><span class="pill">' . admin_h((string) $row['status']) . '</span></td><td>' . admin_h((string) ($row['hired_at'] ?? '—')) . '</td></tr>';
    }
    if ($list === []) {
        admin_table_empty_row(5, admin_t('No employees.', 'لا موظفين.'));
    }
    admin_table_close();
    admin_panel_close();
}

if ($tab === 'departments') {
    admin_panel_open(admin_t('Departments', 'الأقسام'));
    $stmt = $pdo->prepare('SELECT id, name, created_at FROM hr_departments WHERE company_user_id = ? ORDER BY name');
    $stmt->execute([$id]);
    $list = $stmt->fetchAll();
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Name', 'الاسم')) . '</th><th>' . admin_h(admin_t('Created', 'أُنشئ')) . '</th></tr>';
    foreach ($list as $row) {
        echo '<tr><td>' . admin_h((string) $row['name']) . '</td><td>' . admin_h((string) $row['created_at']) . '</td></tr>';
    }
    if ($list === []) {
        admin_table_empty_row(2, admin_t('No departments.', 'لا أقسام.'));
    }
    admin_table_close();
    admin_panel_close();
}

if ($tab === 'attendance') {
    admin_panel_open(admin_t('Recent attendance', 'آخر الحضور'));
    $stmt = $pdo->prepare('SELECT a.work_date, a.hours_worked, e.full_name
        FROM hr_attendance a INNER JOIN hr_employees e ON e.id = a.employee_id AND e.company_user_id = a.company_user_id
        WHERE a.company_user_id = ? ORDER BY a.work_date DESC, a.id DESC LIMIT 120');
    $stmt->execute([$id]);
    $list = $stmt->fetchAll();
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Date', 'التاريخ')) . '</th><th>' . admin_h(admin_t('Employee', 'الموظف')) . '</th><th>' . admin_h(admin_t('Hours', 'الساعات')) . '</th></tr>';
    foreach ($list as $row) {
        echo '<tr><td>' . admin_h((string) $row['work_date']) . '</td><td>' . admin_h((string) $row['full_name']) . '</td><td>' . admin_h((string) $row['hours_worked']) . '</td></tr>';
    }
    if ($list === []) {
        admin_table_empty_row(3, admin_t('No attendance rows.', 'لا سجلات حضور.'));
    }
    admin_table_close();
    admin_panel_close();
}

if ($tab === 'leave') {
    admin_panel_open(admin_t('Leave requests', 'طلبات الإجازة'));
    $stmt = $pdo->prepare("SELECT l.id, l.leave_type, l.start_date, l.end_date, l.status, e.full_name
        FROM hr_leave_requests l INNER JOIN hr_employees e ON e.id = l.employee_id AND e.company_user_id = l.company_user_id
        WHERE l.company_user_id = ? ORDER BY l.id DESC LIMIT 120");
    $stmt->execute([$id]);
    $list = $stmt->fetchAll();
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Employee', 'الموظف')) . '</th><th>' . admin_h(admin_t('Type', 'النوع')) . '</th><th>' . admin_h(admin_t('From', 'من')) . '</th><th>' . admin_h(admin_t('To', 'إلى')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th></tr>';
    foreach ($list as $row) {
        $st = (string) $row['status'];
        echo '<tr><td>' . admin_h((string) $row['full_name']) . '</td><td>' . admin_h((string) $row['leave_type']) . '</td>';
        echo '<td>' . admin_h((string) $row['start_date']) . '</td><td>' . admin_h((string) $row['end_date']) . '</td>';
        echo '<td><span class="pill' . admin_pill_for_status($st) . '">' . admin_h($st) . '</span></td></tr>';
    }
    if ($list === []) {
        admin_table_empty_row(5, admin_t('No leave requests.', 'لا طلبات إجازة.'));
    }
    admin_table_close();
    admin_panel_close();
}

if ($tab === 'payroll') {
    admin_panel_open(admin_t('Payroll records', 'سجلات الرواتب'));
    $stmt = $pdo->prepare("SELECT p.period_month, p.gross_halalas, p.deductions_halalas, p.net_halalas, p.status, e.full_name
        FROM hr_payroll_records p INNER JOIN hr_employees e ON e.id = p.employee_id AND e.company_user_id = p.company_user_id
        WHERE p.company_user_id = ? ORDER BY p.period_month DESC, p.id DESC LIMIT 150");
    $stmt->execute([$id]);
    $list = $stmt->fetchAll();
    admin_table_open();
    echo '<tr><th>' . admin_h(admin_t('Month', 'الشهر')) . '</th><th>' . admin_h(admin_t('Employee', 'الموظف')) . '</th><th>' . admin_h(admin_t('Gross SAR', 'إجمالي')) . '</th><th>' . admin_h(admin_t('Deductions', 'خصومات')) . '</th><th>' . admin_h(admin_t('Net', 'صافي')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th></tr>';
    foreach ($list as $row) {
        echo '<tr><td>' . admin_h((string) $row['period_month']) . '</td><td>' . admin_h((string) $row['full_name']) . '</td>';
        echo '<td>' . admin_h(admin_halalas_to_sar((int) $row['gross_halalas'])) . '</td><td>' . admin_h(admin_halalas_to_sar((int) $row['deductions_halalas'])) . '</td>';
        echo '<td>' . admin_h(admin_halalas_to_sar((int) $row['net_halalas'])) . '</td><td><span class="pill">' . admin_h((string) $row['status']) . '</span></td></tr>';
    }
    if ($list === []) {
        admin_table_empty_row(6, admin_t('No payroll rows.', 'لا سجلات رواتب.'));
    }
    admin_table_close();
    admin_panel_close();
}

admin_end();