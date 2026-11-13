<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cid = $hrCompanyId;
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $pdo->prepare('INSERT INTO hr_departments (company_user_id, name) VALUES (?, ?)')->execute([$cid, $name]);
            $notice = 'saved';
        } else {
            $notice = 'invalid';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (hr_verify_department_belongs($pdo, $cid, $id)) {
            $pdo->prepare('DELETE FROM hr_departments WHERE id = ? AND company_user_id = ?')->execute([$id, $cid]);
            $notice = 'deleted';
        } else {
            $notice = 'forbidden';
        }
    }
}

$stmt = $pdo->prepare('SELECT d.id, d.name, d.created_at, (SELECT COUNT(*) FROM hr_employees e WHERE e.department_id = d.id AND e.company_user_id = d.company_user_id) AS headcount FROM hr_departments d WHERE d.company_user_id = ? ORDER BY d.name');
$stmt->execute([$cid]);
$rows = $stmt->fetchAll();

hr_start(hr_t('Departments', 'الأقسام'));
if ($notice !== '') {
    echo '<p class="flash">' . hr_h(hr_flash($notice)) . '</p>';
}
hr_toolbar([
    ['href' => '/hr/employees.php', 'label' => hr_t('Employees', 'الموظفون')],
]);
hr_panel_open(hr_t('Add department', 'إضافة قسم'), hr_t('Create departments before assigning employees.', 'أنشئ الأقسام قبل ربط الموظفين بها.'));
echo '<form method="post" class="search"><input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="add">';
echo '<input name="name" required placeholder="' . hr_h(hr_t('Department name', 'اسم القسم')) . '"><button type="submit" class="btn primary">' . hr_h(hr_t('Add department', 'إضافة قسم')) . '</button></form>';
hr_panel_close();
hr_panel_open(hr_t('All departments', 'كل الأقسام'));
echo '<table><tr><th>' . hr_h(hr_t('Name', 'الاسم')) . '</th><th>' . hr_h(hr_t('Employees', 'الموظفون')) . '</th><th>' . hr_h(hr_t('Created', 'أُنشئ')) . '</th><th>' . hr_h(hr_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    echo '<tr><td>' . hr_h((string) $row['name']) . '</td><td>' . (int) $row['headcount'] . '</td><td>' . hr_h((string) $row['created_at']) . '</td><td><div class="table-actions">';
    echo '<form method="post" class="inline" onsubmit="return confirm(\'' . hr_h(hr_t('Delete this department?', 'حذف هذا القسم؟')) . '\')">';
    echo '<input type="hidden" name="csrf_token" value="' . hr_h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '">';
    echo '<button type="submit" class="btn small danger">' . hr_h(hr_t('Delete', 'حذف')) . '</button></form></div></td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="4">' . hr_h(hr_t('No departments.', 'لا توجد أقسام.')) . '</td></tr>';
}
echo '</table>';
hr_panel_close();
hr_end();
