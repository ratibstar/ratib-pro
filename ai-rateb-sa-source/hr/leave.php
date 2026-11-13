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
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
echo '<form method="post" class="card"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="request">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '</label><select name="employee_id" required>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select><label>' . hr_h(hr_t('Type', 'النوع')) . '</label><select name="leave_type"><option value="annual">annual</option><option value="sick">sick</option><option value="unpaid">unpaid</option></select>';
echo '<label>' . hr_h(hr_t('Start', 'البداية')) . '</label><input type="date" name="start_date" required>';
echo '<label>' . hr_h(hr_t('End', 'النهاية')) . '</label><input type="date" name="end_date" required>';
echo '<label>' . hr_h(hr_t('Reason', 'السبب')) . '</label><input name="reason"><p><button type="submit">' . hr_h(hr_t('Submit request', 'إرسال الطلب')) . '</button></p></form>';
echo '<table><tr><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Dates', 'التواريخ')) . '</th><th>' . hr_h(hr_t('Type', 'النوع')) . '</th><th>' . hr_h(hr_t('Status', 'الحالة')) . '</th><th></th></tr>';
foreach ($rows as $row) {
    $pill = match ((string) $row['status']) {
        'approved' => 'ok',
        'rejected' => 'bad',
        default => 'warn',
    };
    echo '<tr><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['start_date']) . ' → ' . hr_h((string) $row['end_date']) . '</td><td>' . hr_h((string) $row['leave_type']) . '</td>';
    echo '<td><span class="pill ' . $pill . '">' . hr_h((string) $row['status']) . '</span></td><td>';
    if ((string) $row['status'] === 'pending') {
        foreach (['approved', 'rejected'] as $st) {
            echo '<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="decide"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><input type="hidden" name="status" value="' . $st . '"><button type="submit" class="btn quiet">' . hr_h($st) . '</button></form> ';
        }
    }
    echo '</td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="5">' . hr_h(hr_t('No leave requests.', 'لا طلبات إجازة.')) . '</td></tr>';
}
echo '</table>';
hr_end();
