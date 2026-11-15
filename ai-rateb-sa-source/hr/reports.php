<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$month = trim((string) ($_GET['month'] ?? date('Y-m')));

$byDept = $pdo->prepare('SELECT COALESCE(d.name, ?) AS department, COUNT(*) AS cnt FROM hr_employees e LEFT JOIN hr_departments d ON d.id = e.department_id AND d.company_user_id = e.company_user_id WHERE e.company_user_id = ? GROUP BY department ORDER BY cnt DESC');
$byDept->execute([hr_t('Unassigned', 'بدون قسم'), $cid]);
$deptRows = $byDept->fetchAll();

$hours = $pdo->prepare('SELECT COALESCE(SUM(hours_worked),0) FROM hr_attendance WHERE company_user_id = ? AND work_date LIKE ?');
$hours->execute([$cid, $month . '%']);
$monthHours = (float) $hours->fetchColumn();

$payroll = $pdo->prepare("SELECT COALESCE(SUM(net_halalas),0) FROM hr_payroll_records WHERE company_user_id = ? AND period_month = ? AND status IN ('final','paid')");
$payroll->execute([$cid, $month]);
$monthPayroll = (int) $payroll->fetchColumn();

$leaveStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM hr_leave_requests WHERE company_user_id = ? AND start_date LIKE ? GROUP BY status");
$leaveStmt->execute([$cid, $month . '%']);
$leaveByStatus = [];
foreach ($leaveStmt->fetchAll() as $lr) {
    $leaveByStatus[(string) $lr['status']] = (int) $lr['cnt'];
}

hr_start(hr_t('HR reports', 'تقارير الموارد البشرية'));
hr_toolbar([
    ['href' => '/hr/export.php?kind=employees', 'label' => hr_t('Export employees', 'تصدير الموظفين')],
    ['href' => '/hr/export.php?kind=leave', 'label' => hr_t('Export leave', 'تصدير الإجازات')],
]);
echo '<form class="search" method="get"><input name="month" pattern="\\d{4}-\\d{2}" value="' . hr_h($month) . '"><button type="submit" class="btn quiet">' . hr_h(hr_t('Month', 'الشهر')) . '</button></form>';
echo '<section class="stats">';
echo '<article class="stat"><b>' . hr_h((string) $monthHours) . '</b><span>' . hr_h(hr_t('Attendance hours', 'ساعات الحضور')) . '</span></article>';
echo '<article class="stat"><b>' . hr_h(hr_money_sar($monthPayroll)) . '</b><span>' . hr_h(hr_t('Payroll net (SAR)', 'صافي الرواتب')) . '</span></article>';
echo '<article class="stat"><b>' . (int) ($leaveByStatus['pending'] ?? 0) . '</b><span>' . hr_h(hr_t('Leave pending', 'إجازات معلّقة')) . '</span></article>';
echo '<article class="stat"><b>' . (int) ($leaveByStatus['approved'] ?? 0) . '</b><span>' . hr_h(hr_t('Leave approved', 'إجازات موافق عليها')) . '</span></article>';
echo '</section>';
hr_panel_open(hr_t('Headcount by department', 'العدد حسب القسم'));
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Department', 'القسم')) . '</th><th>' . hr_h(hr_t('Employees', 'الموظفون')) . '</th></tr>';
foreach ($deptRows as $row) {
    echo '<tr><td>' . hr_h((string) $row['department']) . '</td><td>' . (int) $row['cnt'] . '</td></tr>';
}
if ($deptRows === []) {
    hr_table_empty_row(2, hr_t('No data.', 'لا بيانات.'));
}
hr_table_close();
hr_panel_close();
hr_end();