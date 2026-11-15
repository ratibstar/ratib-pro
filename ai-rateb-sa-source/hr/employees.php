<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $empId = (int) ($_POST['id'] ?? 0);
        $deptId = (int) ($_POST['department_id'] ?? 0) ?: null;
        if ($deptId !== null && !hr_verify_department_belongs($pdo, $cid, $deptId)) {
            $notice = 'forbidden';
        } else {
            $fields = [
                'full_name' => trim((string) ($_POST['full_name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'job_title' => trim((string) ($_POST['job_title'] ?? '')),
                'employee_code' => trim((string) ($_POST['employee_code'] ?? '')),
                'status' => in_array((string) ($_POST['status'] ?? ''), ['active', 'inactive', 'terminated'], true) ? (string) $_POST['status'] : 'active',
                'hired_at' => trim((string) ($_POST['hired_at'] ?? '')) ?: null,
                'salary_halalas' => max(0, (int) round(((float) ($_POST['salary_sar'] ?? 0)) * 100)),
            ];
            if ($fields['full_name'] === '') {
                $notice = 'invalid';
            } elseif ($empId > 0 && !hr_verify_employee_belongs($pdo, $cid, $empId)) {
                $notice = 'forbidden';
            } elseif ($empId > 0) {
                $pdo->prepare('UPDATE hr_employees SET department_id=?, employee_code=?, full_name=?, email=?, phone=?, job_title=?, status=?, hired_at=?, salary_halalas=?, notes=? WHERE id=? AND company_user_id=?')
                    ->execute([$deptId, $fields['employee_code'] ?: null, $fields['full_name'], $fields['email'] ?: null, $fields['phone'] ?: null, $fields['job_title'] ?: null, $fields['status'], $fields['hired_at'], $fields['salary_halalas'] ?: null, trim((string) ($_POST['notes'] ?? '')), $empId, $cid]);
                $notice = 'saved';
            } else {
                $pdo->prepare('INSERT INTO hr_employees (company_user_id, department_id, employee_code, full_name, email, phone, job_title, status, hired_at, salary_halalas, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$cid, $deptId, $fields['employee_code'] ?: null, $fields['full_name'], $fields['email'] ?: null, $fields['phone'] ?: null, $fields['job_title'] ?: null, $fields['status'], $fields['hired_at'], $fields['salary_halalas'] ?: null, trim((string) ($_POST['notes'] ?? ''))]);
                $notice = 'saved';
            }
        }
    } elseif ($action === 'delete') {
        $empId = (int) ($_POST['id'] ?? 0);
        if (hr_verify_employee_belongs($pdo, $cid, $empId)) {
            $pdo->prepare('DELETE FROM hr_employees WHERE id = ? AND company_user_id = ?')->execute([$empId, $cid]);
            $notice = 'deleted';
        } else {
            $notice = 'forbidden';
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0 && hr_verify_employee_belongs($pdo, $cid, $editId)) {
    $stmt = $pdo->prepare('SELECT * FROM hr_employees WHERE id = ? AND company_user_id = ?');
    $stmt->execute([$editId, $cid]);
    $edit = $stmt->fetch() ?: null;
}

$depts = $pdo->prepare('SELECT id, name FROM hr_departments WHERE company_user_id = ? ORDER BY name');
$depts->execute([$cid]);
$deptRows = $depts->fetchAll();

$list = $pdo->prepare('SELECT e.*, d.name AS department_name FROM hr_employees e LEFT JOIN hr_departments d ON d.id = e.department_id AND d.company_user_id = e.company_user_id WHERE e.company_user_id = ? ORDER BY e.id DESC');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Employees', 'الموظفون'));
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
hr_toolbar([
    ['href' => '/hr/employees.php', 'label' => hr_t('Add employee', 'إضافة موظف'), 'class' => 'primary'],
    ['href' => '/hr/departments.php', 'label' => hr_t('Departments', 'الأقسام')],
    ['href' => '/hr/export.php?kind=employees', 'label' => hr_t('Export CSV', 'تصدير CSV')],
]);
hr_panel_open($edit ? hr_t('Edit employee', 'تعديل موظف') : hr_t('Add employee', 'إضافة موظف'), hr_t('Full add / edit / delete for your company employees.', 'إضافة وتعديل وحذف موظفي شركتك بالكامل.'));
echo '<form method="post" class="form-grid" id="employee-form">';
echo '<input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="save">';
if ($edit) {
    echo '<input type="hidden" name="id" value="' . (int) $edit['id'] . '">';
}
echo '<label>' . hr_h(hr_t('Full name', 'الاسم الكامل')) . '</label><input name="full_name" required value="' . hr_h((string) ($edit['full_name'] ?? '')) . '">';
echo '<label>' . hr_h(hr_t('Employee code', 'رمز الموظف')) . '</label><input name="employee_code" value="' . hr_h((string) ($edit['employee_code'] ?? '')) . '">';
echo '<label>' . hr_h(hr_t('Department', 'القسم')) . '</label><select name="department_id"><option value="">—</option>';
foreach ($deptRows as $d) {
    $sel = (int) ($edit['department_id'] ?? 0) === (int) $d['id'] ? ' selected' : '';
    echo '<option value="' . (int) $d['id'] . '"' . $sel . '>' . hr_h((string) $d['name']) . '</option>';
}
echo '</select>';
echo '<label>' . hr_h(hr_t('Job title', 'المسمى الوظيفي')) . '</label><input name="job_title" value="' . hr_h((string) ($edit['job_title'] ?? '')) . '">';
echo '<label>' . hr_h(hr_t('Email', 'البريد')) . '</label><input name="email" value="' . hr_h((string) ($edit['email'] ?? '')) . '">';
echo '<label>' . hr_h(hr_t('Phone', 'الهاتف')) . '</label><input name="phone" value="' . hr_h((string) ($edit['phone'] ?? '')) . '">';
echo '<label>' . hr_h(hr_t('Status', 'الحالة')) . '</label><select name="status">';
foreach (['active', 'inactive', 'terminated'] as $st) {
    $sel = ((string) ($edit['status'] ?? 'active')) === $st ? ' selected' : '';
    echo '<option value="' . $st . '"' . $sel . '>' . hr_h(hr_employee_status_label($st)) . '</option>';
}
echo '</select>';
echo '<label>' . hr_h(hr_t('Hire date', 'تاريخ التعيين')) . '</label><input type="date" name="hired_at" value="' . hr_h((string) ($edit['hired_at'] ?? '')) . '">';
$salarySar = isset($edit['salary_halalas']) ? hr_money_sar((int) $edit['salary_halalas']) : '';
echo '<label>' . hr_h(hr_t('Monthly salary (SAR)', 'الراتب الشهري (ر.س)')) . '</label><input name="salary_sar" inputmode="decimal" value="' . hr_h($salarySar) . '">';
echo '<label>' . hr_h(hr_t('Notes', 'ملاحظات')) . '</label><textarea name="notes" rows="2">' . hr_h((string) ($edit['notes'] ?? '')) . '</textarea>';
echo '<p class="full"><button type="submit" class="btn primary">' . hr_h(hr_t('Save employee', 'حفظ الموظف')) . '</button>';
if ($edit) {
    echo ' <a class="btn quiet" href="/hr/employees.php">' . hr_h(hr_t('Cancel', 'إلغاء')) . '</a>';
}
echo '</p></form>';
hr_panel_close();

hr_panel_open(hr_t('All employees', 'كل الموظفين'));
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Name', 'الاسم')) . '</th><th>' . hr_h(hr_t('Department', 'القسم')) . '</th><th>' . hr_h(hr_t('Title', 'المسمى')) . '</th><th>' . hr_h(hr_t('Status', 'الحالة')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $st = (string) $row['status'];
    echo '<tr><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) ($row['department_name'] ?? '—')) . '</td><td>' . hr_h((string) ($row['job_title'] ?? '—')) . '</td>';
    echo '<td><span class="pill ' . hr_pill_class($st) . '">' . hr_h(hr_employee_status_label($st)) . '</span></td><td><div class="table-actions">';
    echo '<a class="btn small quiet" href="/hr/employees.php?edit=' . (int) $row['id'] . '">' . hr_h(hr_t('Edit', 'تعديل')) . '</a>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'' . hr_h(hr_t('Delete this employee?', 'حذف هذا الموظف؟')) . '\')">';
    echo '<input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '">';
    echo '<button type="submit" class="btn small danger">' . hr_h(hr_t('Delete', 'حذف')) . '</button></form></div></td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="5">' . hr_h(hr_t('No employees yet.', 'لا يوجد موظفون بعد.')) . '</td></tr>';
}
hr_table_close();
hr_panel_close();
hr_end();
