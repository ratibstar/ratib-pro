<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $empId = (int) ($_POST['employee_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $label = trim((string) ($_POST['file_label'] ?? ''));
    if (!hr_verify_employee_belongs($pdo, $cid, $empId) || $title === '' || $label === '') {
        $notice = 'invalid';
    } else {
        $pdo->prepare('INSERT INTO hr_documents (company_user_id, employee_id, title, file_label, storage_note) VALUES (?,?,?,?,?)')
            ->execute([$cid, $empId, $title, $label, trim((string) ($_POST['storage_note'] ?? '')) ?: null]);
        $notice = 'saved';
    }
}

$employees = $pdo->prepare('SELECT id, full_name FROM hr_employees WHERE company_user_id = ? ORDER BY full_name');
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$list = $pdo->prepare('SELECT d.*, e.full_name FROM hr_documents d INNER JOIN hr_employees e ON e.id = d.employee_id AND e.company_user_id = d.company_user_id WHERE d.company_user_id = ? ORDER BY d.id DESC LIMIT 100');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Documents', 'المستندات'));
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
echo '<p class="note">' . hr_h(hr_t('Document registry stores titles and file references. Upload storage is configured separately.', 'سجل المستندات يحفظ العناوين ومراجع الملفات. التخزين يُعدّ بشكل منفصل.')) . '</p>';
echo '<form method="post" class="card"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '</label><select name="employee_id" required>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select><label>' . hr_h(hr_t('Title', 'العنوان')) . '</label><input name="title" required>';
echo '<label>' . hr_h(hr_t('File name / reference', 'اسم الملف / المرجع')) . '</label><input name="file_label" required>';
echo '<label>' . hr_h(hr_t('Storage note', 'ملاحظة التخزين')) . '</label><input name="storage_note"><p><button type="submit">' . hr_h(hr_t('Add record', 'إضافة سجل')) . '</button></p></form>';
echo '<table><tr><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Title', 'العنوان')) . '</th><th>' . hr_h(hr_t('Reference', 'المرجع')) . '</th><th>' . hr_h(hr_t('Added', 'أُضيف')) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['title']) . '</td><td>' . hr_h((string) $row['file_label']) . '</td><td>' . hr_h((string) $row['created_at']) . '</td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="4">' . hr_h(hr_t('No documents.', 'لا مستندات.')) . '</td></tr>';
}
echo '</table>';
hr_end();
