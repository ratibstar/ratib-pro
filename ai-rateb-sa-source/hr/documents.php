<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/hr/document-storage.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $docId = (int) ($_POST['id'] ?? 0);
        $chk = $pdo->prepare('SELECT id, storage_path FROM hr_documents WHERE id = ? AND company_user_id = ?');
        $chk->execute([$docId, $cid]);
        $old = $chk->fetch();
        if ($old) {
            hr_document_unlink($old['storage_path'] ?? null);
            $pdo->prepare('DELETE FROM hr_documents WHERE id = ? AND company_user_id = ?')->execute([$docId, $cid]);
            $notice = 'deleted';
        } else {
            $notice = 'forbidden';
        }
    } else {
        $empId = (int) ($_POST['employee_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if (!hr_verify_employee_belongs($pdo, $cid, $empId) || $title === '') {
            $notice = 'invalid';
        } else {
            $label = trim((string) ($_POST['file_label'] ?? ''));
            $path = null;
            $upload = $_FILES['file'] ?? null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = hr_document_store_upload($cid, $upload);
                if (!$stored['ok']) {
                    $notice = (string) ($stored['code'] ?? 'upload');
                } else {
                    $path = (string) $stored['path'];
                    if ($label === '') {
                        $label = (string) $stored['label'];
                    }
                }
            } elseif ($label === '') {
                $notice = 'invalid';
            }
            if ($notice === '') {
                $pdo->prepare('INSERT INTO hr_documents (company_user_id, employee_id, title, file_label, storage_path, storage_note) VALUES (?,?,?,?,?,?)')
                    ->execute([$cid, $empId, $title, $label, $path, trim((string) ($_POST['storage_note'] ?? '')) ?: null]);
                $notice = 'saved';
            }
        }
    }
}

$employees = $pdo->prepare('SELECT id, full_name FROM hr_employees WHERE company_user_id = ? ORDER BY full_name');
$employees->execute([$cid]);
$empRows = $employees->fetchAll();

$list = $pdo->prepare('SELECT d.*, e.full_name FROM hr_documents d INNER JOIN hr_employees e ON e.id = d.employee_id AND e.company_user_id = d.company_user_id WHERE d.company_user_id = ? ORDER BY d.id DESC LIMIT 200');
$list->execute([$cid]);
$rows = $list->fetchAll();

hr_start(hr_t('Documents', 'المستندات'));
hr_toolbar([
    ['href' => '/hr/employees.php', 'label' => hr_t('Employees', 'الموظفون')],
    ['href' => '/hr/export.php?kind=employees', 'label' => hr_t('Export employees', 'تصدير الموظفين')],
]);
if ($notice !== '') {
    hr_flash_box($notice);
}
hr_panel_open(hr_t('Register document', 'تسجيل مستند'), hr_t('Upload PDF or images (max 5 MB), or save a reference name only.', 'ارفع PDF أو صورًا (حد 5 ميجا)، أو احفظ اسم مرجع فقط.'));
echo '<form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '">';
echo '<label>' . hr_h(hr_t('Employee', 'الموظف')) . '<select name="employee_id" required>';
foreach ($empRows as $e) {
    echo '<option value="' . (int) $e['id'] . '">' . hr_h((string) $e['full_name']) . '</option>';
}
echo '</select></label><label>' . hr_h(hr_t('Title', 'العنوان')) . '<input name="title" required></label>';
echo '<label>' . hr_h(hr_t('File (optional)', 'ملف (اختياري)')) . '<input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.webp"></label>';
echo '<label>' . hr_h(hr_t('File name / reference', 'اسم الملف / المرجع')) . '<input name="file_label" placeholder="' . hr_h(hr_t('If no upload', 'إن لم ترفع ملفًا')) . '"></label>';
echo '<label class="full">' . hr_h(hr_t('Storage note', 'ملاحظة التخزين')) . '<input name="storage_note"></label>';
echo '<p class="full"><button type="submit" class="btn primary">' . hr_h(hr_t('Add record', 'إضافة سجل')) . '</button></p></form>';
hr_panel_close();
hr_panel_open(hr_t('Document registry', 'سجل المستندات'));
hr_table_open();
echo '<tr><th>' . hr_h(hr_t('Employee', 'الموظف')) . '</th><th>' . hr_h(hr_t('Title', 'العنوان')) . '</th><th>' . hr_h(hr_t('Reference', 'المرجع')) . '</th><th>' . hr_h(hr_t('File', 'ملف')) . '</th><th>' . hr_h(hr_t('Added', 'أُضيف')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['full_name']) . '</td><td>' . hr_h((string) $row['title']) . '</td><td>' . hr_h((string) $row['file_label']) . '</td><td>';
    if (($row['storage_path'] ?? '') !== '') {
        echo '<a class="btn small quiet" href="/hr/document.php?id=' . (int) $row['id'] . '" target="_blank" rel="noopener">' . hr_h(hr_t('Download', 'تنزيل')) . '</a>';
    } else {
        echo '—';
    }
    echo '</td><td>' . hr_h((string) $row['created_at']) . '</td><td>';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'' . hr_h(hr_t('Delete?', 'حذف؟')) . '\')"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button type="submit" class="btn small danger">' . hr_h(hr_t('Delete', 'حذف')) . '</button></form></td></tr>';
}
if ($rows === []) {
    hr_table_empty_row(6, hr_t('No documents.', 'لا مستندات.'));
}
hr_table_close();
hr_panel_close();
hr_end();