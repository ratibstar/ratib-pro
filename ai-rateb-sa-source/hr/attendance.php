<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $empId = (int) ($_POST['employee_id'] ?? 0);
    $workDate = trim((string) ($_POST['work_date'] ?? ''));
    $hours = (float) ($_POST['hours_worked'] ?? 0);
    if (!hr_verify_employee_belongs($pdo, $cid, $empId) || $workDate === '' || $hours < 0) {
        $notice = 'invalid';
    } else {
        $pdo->prepare('INSERT INTO hr_attendance (company_user_id, employee_id, work_date, hours_worked, note) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE hours_worked = VALUES(hours_worked), note = VALUES(note)')
            ->execute([$cid, $empId, $workDate, $hours, trim((string) ($_POST['note'] ?? '')) ?: null]);
        $notice = 'saved';
    }
}

$employees = $pdo->prepare("SELECT id, full_name FROM hr_employees WHERE company_user_id = ? AND status = 'active' ORDER BY full_name");
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
$list = $pdo->prepare('SELECT a.work_date, a.hours_worked, a.note, e.full_name FROM hr_attendance a INNER JOIN hr_employees e ON e.id = a.employee_id AND e.company_user_id = a.company_user_id WHERE a.company_user_id = ? AND a.work_date BETWEEN ? AND ? ORDER BY a.work_date DESC, e.full_name');
$list->execute([$cid, $from, $to]);
$rows = $list->fetchAll();

hr_start(hr_t('Attendance', 'الحضور'));
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
echo '<form method="post" class="card"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '</label><select name="employee_id" required><option value="">—</option>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select><label>' . hr_h(hr_t('Date', 'التاريخ')) . '</label><input type="date" name="work_date" required value="' . hr_h(date('Y-m-d')) . '">';
echo '<label>' . hr_h(hr_t('Hours worked', 'ساعات العمل')) . '</label><input name="hours_worked" type="number" step="0.25" min="0" max="24" required value="8">';
echo '<label>' . hr_h(hr_t('Note', 'ملاحظة')) . '</label><input name="note"><p><button type="submit">' . hr_h(hr_t('Save entry', 'حفظ')) . '</button></p></form>';
echo '<form class="search" method="get"><input type="date" name="from" value="' . hr_h($from) . '"><input type="date" name="to" value="' . hr_h($to) . '"><button type="submit">' . hr_h(hr_t('Filter', 'تصفية')) . '</button></form>';
echo '<table><tr><th>' . hr_h(hr_t('Date', 'التاريخ')) . '</th><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Hours', 'الساعات')) . '</th><th>' . hr_h(hr_t('Note', 'ملاحظة')) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['work_date']) . '</td><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['hours_worked']) . '</td><td>' . hr_h((string) ($row['note'] ?? '')) . '</td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="4">' . hr_h(hr_t('No attendance in range.', 'لا سجلات في الفترة.')) . '</td></tr>';
}
echo '</table>';
hr_end();
