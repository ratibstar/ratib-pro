<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $pid = (int) ($_POST['id'] ?? 0);
        $chk = $pdo->prepare('SELECT id FROM hr_payroll_records WHERE id = ? AND company_user_id = ?');
        $chk->execute([$pid, $cid]);
        if ($chk->fetchColumn()) {
            $pdo->prepare('DELETE FROM hr_payroll_records WHERE id = ? AND company_user_id = ?')->execute([$pid, $cid]);
            $notice = 'deleted';
        } else {
            $notice = 'forbidden';
        }
    } elseif ($action === 'generate_month') {
        $period = trim((string) ($_POST['period_month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $notice = 'invalid';
        } else {
            $emps = $pdo->prepare("SELECT id, salary_halalas FROM hr_employees WHERE company_user_id = ? AND status = 'active'");
            $emps->execute([$cid]);
            $ins = $pdo->prepare('INSERT INTO hr_payroll_records (company_user_id, employee_id, period_month, gross_halalas, deductions_halalas, net_halalas, status)
                VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE gross_halalas=VALUES(gross_halalas), net_halalas=VALUES(net_halalas)');
            foreach ($emps->fetchAll() as $emp) {
                $gross = (int) ($emp['salary_halalas'] ?? 0);
                $ins->execute([$cid, (int) $emp['id'], $period, $gross, 0, $gross, 'draft']);
            }
            $notice = 'saved';
        }
    } else {
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
}

$employees = $pdo->prepare('SELECT id, full_name, salary_halalas FROM hr_employees WHERE company_user_id = ? ORDER BY full_name');
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$list = $pdo->prepare('SELECT p.*, e.full_name FROM hr_payroll_records p INNER JOIN hr_employees e ON e.id = p.employee_id AND e.company_user_id = p.company_user_id WHERE p.company_user_id = ? ORDER BY p.period_month DESC, e.full_name LIMIT 300');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Payroll', 'الرواتب'));
hr_toolbar([
    ['href' => '/hr/export.php?kind=payroll', 'label' => hr_t('Export CSV', 'تصدير CSV')],
    ['href' => '/hr/reports.php', 'label' => hr_t('Reports', 'التقارير')],
]);
if ($notice !== '') {
    hr_flash_box($notice);
}
hr_panel_open(hr_t('Generate month drafts', 'إنشاء مسودات الشهر'), hr_t('Creates draft payroll rows from each active employee salary.', 'ينشئ مسودات من راتب كل موظف نشط.'));
echo '<form method="post" class="search"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="generate_month">';
echo '<input name="period_month" pattern="\\d{4}-\\d{2}" required value="' . hr_h(date('Y-m')) . '">';
echo '<button type="submit" class="btn primary">' . hr_h(hr_t('Generate for all active', 'إنشاء للجميع النشطين')) . '</button></form>';
hr_panel_close();
hr_panel_open(hr_t('Edit payroll row', 'تعديل سطر راتب'));
echo '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '<select name="employee_id" required id="pay-emp">';
foreach ($empRows as $e) {
    $sal = isset($e['salary_halalas']) ? hr_money_sar((int) $e['salary_halalas']) : '';
    echo '<option value="' . (int) $e['id'] . '" data-salary="' . hr_h($sal) . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select></label><label>' . hr_h(hr_t('Period (YYYY-MM)', 'الفترة')) . '<input name="period_month" pattern="\\d{4}-\\d{2}" required value="' . hr_h(date('Y-m')) . '"></label>';
echo '<label>' . hr_h(hr_t('Gross (SAR)', 'الإجمالي')) . '<input name="gross_sar" id="pay-gross" inputmode="decimal" required></label>';
echo '<label>' . hr_h(hr_t('Deductions (SAR) — GOSI / tax', 'الاستقطاعات — غسوس / ضريبة')) . '<input name="deductions_sar" inputmode="decimal" value="0"></label>';
echo '<label>' . hr_h(hr_t('Status', 'الحالة')) . '<select name="status">';
foreach (['draft', 'final', 'paid'] as $st) {
    echo '<option value="' . $st . '">' . hr_h(hr_payroll_status_label($st)) . '</option>';
}
echo '</select></label><label class="full">' . hr_h(hr_t('Notes', 'ملاحظات')) . '<input name="notes"></label>';
echo '<p class="full"><button type="submit" class="btn primary">' . hr_h(hr_t('Save payroll', 'حفظ الراتب')) . '</button></p></form>';
echo '<script>document.getElementById("pay-emp")?.addEventListener("change",function(){var o=this.selectedOptions[0];var g=document.getElementById("pay-gross");if(g&&o){g.value=o.dataset.salary||"";}});document.getElementById("pay-emp")?.dispatchEvent(new Event("change"));</script>';
hr_panel_close();
hr_panel_open(hr_t('Payroll register', 'سجل الرواتب'));
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Period', 'الفترة')) . '</th><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Gross', 'الإجمالي')) . '</th><th>' . hr_h(hr_t('Net SAR', 'صافي ر.س')) . '</th><th>' . hr_h(hr_t('Status', 'الحالة')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $st = (string) $row['status'];
    echo '<tr><td>' . hr_h((string) $row['period_month']) . '</td><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h(hr_money_sar((int) $row['gross_halalas'])) . '</td><td>' . hr_h(hr_money_sar((int) $row['net_halalas'])) . '</td>';
    echo '<td><span class="pill ' . hr_pill_class($st) . '">' . hr_h(hr_payroll_status_label($st)) . '</span></td><td>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'' . hr_h(hr_t('Delete row?', 'حذف السطر؟')) . '\')"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button type="submit" class="btn small danger">' . hr_h(hr_t('Delete', 'حذف')) . '</button></form></td></tr>';
}
if ($rows === []) {
    hr_table_empty_row(6, hr_t('No payroll records.', 'لا سجلات رواتب.'));
}
hr_table_close();
hr_panel_close();
hr_end();