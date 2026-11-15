<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'request') {
        $empId = (int) ($_POST['employee_id'] ?? 0);
        $start = trim((string) ($_POST['start_date'] ?? ''));
        $end = trim((string) ($_POST['end_date'] ?? ''));
        if (!hr_verify_employee_belongs($pdo, $cid, $empId) || $start === '' || $end === '' || $end < $start) {
            $notice = 'invalid';
        } else {
            $pdo->prepare('INSERT INTO hr_leave_requests (company_user_id, employee_id, leave_type, start_date, end_date, reason) VALUES (?,?,?,?,?,?)')
                ->execute([$cid, $empId, (string) ($_POST['leave_type'] ?? 'annual'), $start, $end, trim((string) ($_POST['reason'] ?? '')) ?: null]);
            $notice = 'saved';
        }
    } elseif ($action === 'decide') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            $notice = 'invalid';
        } else {
            $chk = $pdo->prepare('SELECT id FROM hr_leave_requests WHERE id = ? AND company_user_id = ?');
            $chk->execute([$id, $cid]);
            if ($chk->fetchColumn()) {
                $pdo->prepare('UPDATE hr_leave_requests SET status = ?, decided_at = NOW() WHERE id = ? AND company_user_id = ?')->execute([$status, $id, $cid]);
                $notice = 'saved';
            } else {
                $notice = 'forbidden';
            }
        }
    }
}

$employees = $pdo->prepare('SELECT id, full_name FROM hr_employees WHERE company_user_id = ? ORDER BY full_name');
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$list = $pdo->prepare('SELECT l.*, e.full_name FROM hr_leave_requests l INNER JOIN hr_employees e ON e.id = l.employee_id AND e.company_user_id = l.company_user_id WHERE l.company_user_id = ? ORDER BY l.id DESC LIMIT 100');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Leave', 'الإجازات'));
hr_toolbar([['href' => '/hr/employees.php', 'label' => hr_t('Employees', 'الموظفون')]]);
if ($notice !== '') {
    hr_flash_box($notice);
}
hr_panel_open(hr_t('New leave request', 'طلب إجازة جديد'));
echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="request">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '<select name="employee_id" required>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select></label><label>' . hr_h(hr_t('Type', 'النوع')) . '<select name="leave_type">';
foreach (['annual', 'sick', 'unpaid'] as $lt) {
    echo '<option value="' . $lt . '">' . hr_h(hr_leave_type_label($lt)) . '</option>';
}
echo '</select></label><label>' . hr_h(hr_t('Start', 'البداية')) . '<input type="date" name="start_date" required></label>';
echo '<label>' . hr_h(hr_t('End', 'النهاية')) . '<input type="date" name="end_date" required></label>';
echo '<label class="full">' . hr_h(hr_t('Reason', 'السبب')) . '<input name="reason"></label>';
echo '<p class="full"><button type="submit" class="btn primary">' . hr_h(hr_t('Submit request', 'إرسال الطلب')) . '</button></p></form>';
hr_panel_close();
hr_panel_open(hr_t('Leave requests', 'طلبات الإجازة'));
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Dates', 'التواريخ')) . '</th><th>' . hr_h(hr_t('Type', 'النوع')) . '</th><th>' . hr_h(hr_t('Status', 'الحالة')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $st = (string) $row['status'];
    echo '<tr><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['start_date']) . ' → ' . hr_h((string) $row['end_date']) . '</td><td>' . hr_h(hr_leave_type_label((string) $row['leave_type'])) . '</td>';
    echo '<td><span class="pill ' . hr_pill_class($st) . '">' . hr_h(hr_leave_status_label($st)) . '</span></td><td><div class="table-actions">';
    if ($st === 'pending') {
        foreach (['approved' => hr_t('Approve', 'موافقة'), 'rejected' => hr_t('Reject', 'رفض')] as $code => $label) {
            echo '<form method="post" class="inline"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="decide"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="status" value="' . $code . '"><button type="submit" class="btn small ' . ($code === 'rejected' ? 'danger' : 'primary') . '">' . hr_h($label) . '</button></form>';
        }
    }
    echo '</div></td></tr>';
}
if ($rows === []) {
    hr_table_empty_row(5, hr_t('No leave requests.', 'لا طلبات إجازة.'));
}
hr_table_close();
hr_panel_close();
hr_end();
