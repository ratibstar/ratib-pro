<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $empId = (int) ($_POST['employee_id'] ?? 0);
    $period = trim((string) ($_POST['period_month'] ?? ''));
    if (!hr_verify_employee_belongs($pdo, $cid, $empId) || !preg_match('/^\d{4}-\d{2}$/', $period)) {
        $notice = 'invalid';
    } else {
        $gross = max(0, (int) round(((float) ($_POST['gross_sar'] ?? 0)) * 100));
        $ded = max(0, (int) round(((float) ($_POST['deductions_sar'] ?? 0)) * 100));
        $net = max(0, $gross - $ded);
        $status = in_array((string) ($_POST['status'] ?? ''), ['draft', 'final', 'paid'], true) ? (string) $_POST['status'] : 'draft';
        $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('INSERT INTO hr_payroll_records (company_user_id, employee_id, period_month, gross_halalas, deductions_halalas, net_halalas, status, paid_at, notes)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE gross_halalas=VALUES(gross_halalas), deductions_halalas=VALUES(deductions_halalas), net_halalas=VALUES(net_halalas), status=VALUES(status), paid_at=VALUES(paid_at), notes=VALUES(notes)')
            ->execute([$cid, $empId, $period, $gross, $ded, $net, $status, $paidAt, trim((string) ($_POST['notes'] ?? '')) ?: null]);
        $notice = 'saved';
    }
}

$employees = $pdo->prepare('SELECT id, full_name FROM hr_employees WHERE company_user_id = ? ORDER BY full_name');
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$list = $pdo->prepare('SELECT p.*, e.full_name FROM hr_payroll_records p INNER JOIN hr_employees e ON e.id = p.employee_id AND e.company_user_id = p.company_user_id WHERE p.company_user_id = ? ORDER BY p.period_month DESC, e.full_name LIMIT 200');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Payroll', 'الرواتب'));
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
echo '<form method="post" class="card"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '</label><select name="employee_id" required>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select><label>' . hr_h(hr_t('Period (YYYY-MM)', 'الفترة')) . '</label><input name="period_month" pattern="\\d{4}-\\d{2}" required value="' . hr_h(date('Y-m')) . '">';
echo '<label>' . hr_h(hr_t('Gross (SAR)', 'الإجمالي')) . '</label><input name="gross_sar" inputmode="decimal" required>';
echo '<label>' . hr_h(hr_t('Deductions (SAR)', 'الاستقطاعات')) . '</label><input name="deductions_sar" inputmode="decimal" value="0">';
echo '<label>' . hr_h(hr_t('Status', 'الحالة')) . '</label><select name="status"><option value="draft">draft</option><option value="final">final</option><option value="paid">paid</option></select>';
echo '<label>' . hr_h(hr_t('Notes', 'ملاحظات')) . '</label><input name="notes"><p><button type="submit">' . hr_h(hr_t('Save payroll', 'حفظ الراتب')) . '</button></p></form>';
echo '<table><tr><th>' . hr_h(hr_t('Period', 'الفترة')) . '</th><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Net SAR', 'صافي ر.س')) . '</th><th>' . hr_h(hr_t('Status', 'الحالة')) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['period_month']) . '</td><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h(hr_money_sar((int) $row['net_halalas'])) . '</td><td>' . hr_h((string) $row['status']) . '</td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="4">' . hr_h(hr_t('No payroll records.', 'لا سجلات رواتب.')) . '</td></tr>';
}
echo '</table>';
hr_end();
