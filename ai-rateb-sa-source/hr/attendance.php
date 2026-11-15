<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $attId = (int) ($_POST['id'] ?? 0);
        $chk = $pdo->prepare('SELECT id FROM hr_attendance WHERE id = ? AND company_user_id = ?');
        $chk->execute([$attId, $cid]);
        if ($chk->fetchColumn()) {
            $pdo->prepare('DELETE FROM hr_attendance WHERE id = ? AND company_user_id = ?')->execute([$attId, $cid]);
            $notice = 'deleted';
        } else {
            $notice = 'forbidden';
        }
    } else {
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
}

$employees = $pdo->prepare("SELECT id, full_name FROM hr_employees WHERE company_user_id = ? AND status = 'active' ORDER BY full_name");
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
$list = $pdo->prepare('SELECT a.id, a.work_date, a.hours_worked, a.note, e.full_name FROM hr_attendance a INNER JOIN hr_employees e ON e.id = a.employee_id AND e.company_user_id = a.company_user_id WHERE a.company_user_id = ? AND a.work_date BETWEEN ? AND ? ORDER BY a.work_date DESC, e.full_name');
$list->execute([$cid, $from, $to]);
$rows = $list->fetchAll();

hr_start(hr_t('Attendance', 'الحضور'));
hr_toolbar([
    ['href' => '/hr/export.php?kind=attendance&from=' . rawurlencode($from) . '&to=' . rawurlencode($to), 'label' => hr_t('Export CSV', 'تصدير CSV')],
]);
if ($notice !== '') {
    hr_flash_box($notice);
}
hr_panel_open(hr_t('Log attendance', 'تسجيل حضور'));
echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '<select name="employee_id" required><option value="">—</option>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select></label><label>' . hr_h(hr_t('Date', 'التاريخ')) . '<input type="date" name="work_date" required value="' . hr_h(date('Y-m-d')) . '"></label>';
echo '<label>' . hr_h(hr_t('Hours worked', 'ساعات العمل')) . '<input name="hours_worked" type="number" step="0.25" min="0" max="24" required value="8"></label>';
echo '<label class="full">' . hr_h(hr_t('Note', 'ملاحظة')) . '<input name="note"></label>';
echo '<p class="full"><button type="submit" class="btn primary">' . hr_h(hr_t('Save entry', 'حفظ السجل')) . '</button></p></form>';
hr_panel_close();
hr_panel_open(hr_t('Attendance log', 'سجل الحضور'));
echo '<form class="search" method="get"><input type="date" name="from" value="' . hr_h($from) . '"><input type="date" name="to" value="' . hr_h($to) . '"><button type="submit" class="btn quiet">' . hr_h(hr_t('Filter', 'تصفية')) . '</button></form>';
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Date', 'التاريخ')) . '</th><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Hours', 'الساعات')) . '</th><th>' . hr_h(hr_t('Note', 'ملاحظة')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['work_date']) . '</td><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['hours_worked']) . '</td><td>' . hr_h((string) ($row['note'] ?? '')) . '</td><td>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'' . hr_h(hr_t('Delete entry?', 'حذف السجل؟')) . '\')">';
    echo '<input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '">';
    echo '<button type="submit" class="btn small danger">' . hr_h(hr_t('Delete', 'حذف')) . '</button></form></td></tr>';
}
if ($rows === []) {
    hr_table_empty_row(5, hr_t('No attendance in range.', 'لا سجلات في الفترة.'));
}
hr_table_close();
hr_panel_close();
hr_end();