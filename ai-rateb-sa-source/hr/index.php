<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$stats = [
    'employees' => 0,
    'active' => 0,
    'departments' => 0,
    'pending_leave' => 0,
];
$stmt = $pdo->prepare('SELECT COUNT(*) FROM hr_employees WHERE company_user_id = ?');
$stmt->execute([$cid]);
$stats['employees'] = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM hr_employees WHERE company_user_id = ? AND status = 'active'");
$stmt->execute([$cid]);
$stats['active'] = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM hr_departments WHERE company_user_id = ?');
$stmt->execute([$cid]);
$stats['departments'] = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM hr_leave_requests WHERE company_user_id = ? AND status = 'pending'");
$stmt->execute([$cid]);
$stats['pending_leave'] = (int) $stmt->fetchColumn();

hr_start(hr_t('HR overview', 'نظرة الموارد البشرية'));
hr_toolbar([
    ['href' => '/hr/employees.php', 'label' => hr_t('Add employee', 'إضافة موظف'), 'class' => 'primary'],
    ['href' => '/hr/departments.php', 'label' => hr_t('Departments', 'الأقسام')],
    ['href' => '/hr/reports.php', 'label' => hr_t('Reports', 'التقارير')],
]);
echo '<section class="stats">';
foreach (
    [
        [hr_t('Employees', 'الموظفون'), (string) $stats['employees']],
        [hr_t('Active', 'نشط'), (string) $stats['active']],
        [hr_t('Departments', 'الأقسام'), (string) $stats['departments']],
        [hr_t('Pending leave', 'إجازات معلّقة'), (string) $stats['pending_leave']],
    ] as [$label, $value]
) {
    echo '<article class="stat"><b>' . hr_h($value) . '</b><span>' . hr_h($label) . '</span></article>';
}
echo '</section>';
echo '<p class="note">' . hr_h(hr_t(
    'Manage employees, attendance, payroll, and leave from the sidebar sections. Platform admins only monitor usage — they cannot edit your HR here.',
    'أدِر الموظفين والحضور والرواتب والإجازات من أقسام القائمة. مسؤولو المنصة يراقبون الاستخدام فقط ولا يعدّلون بياناتك هنا.'
)) . '</p>';
hr_end();