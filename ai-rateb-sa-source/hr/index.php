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
echo '<div class="stats">';
foreach (
    [
        [hr_t('Employees', 'الموظفون'), (string) $stats['employees']],
        [hr_t('Active', 'نشط'), (string) $stats['active']],
        [hr_t('Departments', 'الأقسام'), (string) $stats['departments']],
        [hr_t('Pending leave', 'إجازات معلّقة'), (string) $stats['pending_leave']],
    ] as [$label, $value]
) {
    echo '<article class="stat"><span>' . hr_h($label) . '</span><b>' . hr_h($value) . '</b></article>';
}
echo '</div>';
echo '<p class="note">' . hr_h(hr_t(
    'HR data is isolated to your company account. Platform administrators use a separate console and cannot manage your employees here.',
    'بيانات الموارد البشرية معزولة لحساب شركتك. مسؤولو المنصة يستخدمون وحدة منفصلة ولا يديرون موظفيك من هنا.'
)) . '</p>';
hr_end();
