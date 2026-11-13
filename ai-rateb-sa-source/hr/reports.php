<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;

$byDept = $pdo->prepare('SELECT COALESCE(d.name, ?) AS department, COUNT(*) AS cnt FROM hr_employees e LEFT JOIN hr_departments d ON d.id = e.department_id AND d.company_user_id = e.company_user_id WHERE e.company_user_id = ? GROUP BY department ORDER BY cnt DESC');
$byDept->execute([hr_t('Unassigned', 'بدون قسم'), $cid]);
$deptRows = $byDept->fetchAll();

$month = date('Y-m');
$hours = $pdo->prepare('SELECT COALESCE(SUM(hours_worked),0) FROM hr_attendance WHERE company_user_id = ? AND work_date LIKE ?');
$hours->execute([$cid, $month . '%']);
$monthHours = (float) $hours->fetchColumn();

$payroll = $pdo->prepare("SELECT COALESCE(SUM(net_halalas),0) FROM hr_payroll_records WHERE company_user_id = ? AND period_month = ? AND status IN ('final','paid')");
$payroll->execute([$cid, $month]);
$monthPayroll = (int) $payroll->fetchColumn();

hr_start(hr_t('HR reports', 'تقارير الموارد البشرية'));
echo '<div class="stats"><article class="stat"><span>' . hr_h(hr_t('Attendance hours this month', 'ساعات الحضور هذا الشهر')) . '</span><b>' . hr_h((string) $monthHours) . '</b></article>';
echo '<article class="stat"><span>' . hr_h(hr_t('Payroll net this month (SAR)', 'صافي الرواتب هذا الشهر')) . '</span><b>' . hr_h(hr_money_sar($monthPayroll)) . '</b></article></div>';
echo '<h2 class="section-title">' . hr_h(hr_t('Headcount by department', 'العدد حسب القسم')) . '</h2><table><tr><th>' . hr_h(hr_t('Department', 'القسم')) . '</th><th>' . hr_h(hr_t('Employees', 'الموظفون')) . '</th></tr>';
foreach ($deptRows as $row) {
    echo '<tr><td>' . hr_h((string) $row['department']) . '</td><td>' . (int) $row['cnt'] . '</td></tr>';
}
if ($deptRows === []) {
    echo '<tr><td colspan="2">' . hr_h(hr_t('No data.', 'لا بيانات.')) . '</td></tr>';
}
echo '</table>';
hr_end();
