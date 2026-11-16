<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('staff.manage');
if (!admin_actor_is_owner($adminActor)) {
    http_response_code(403);
    exit('Owner only.');
}

if (!admin_table_has($pdo, 'admin_staff', 'id')) {
    admin_start(admin_t('Staff', 'الموظفون'));
    echo '<p class="flash">' . admin_h(admin_flash('schema')) . '</p>';
    admin_end();
    exit;
}

$notice = '';
$roles = ['finance', 'support', 'operations', 'viewer'];
$q = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    admin_require_permission('staff.manage');
    $action = (string) ($_POST['action'] ?? '');
    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? 'staff change'));

    if ($action === 'create') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $role = (string) ($_POST['role_code'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!in_array($role, $roles, true) || $email === '' || mb_strlen($name) < 2 || strlen($password) < 8) {
            $notice = 'invalid';
        } else {
            try {
                $pdo->prepare('INSERT INTO admin_staff (email, name, password_hash, role_code, status) VALUES (?, ?, ?, ?, \'active\')')
                    ->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), $role]);
                $newId = (int) $pdo->lastInsertId();
                admin_audit($pdo, $adminActor, 'staff_create', 'staff', $newId, $reason, 'role=' . $role);
                $notice = 'applied';
            } catch (Throwable) {
                $notice = 'duplicate';
            }
        }
    } elseif ($action === 'update' && $staffId > 0) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $role = (string) ($_POST['role_code'] ?? '');
        $target = $pdo->prepare('SELECT id, role_code FROM admin_staff WHERE id = ? LIMIT 1');
        $target->execute([$staffId]);
        $row = $target->fetch();
        if (!$row || (int) ($adminActor['staff_id'] ?? 0) === $staffId) {
            $notice = 'self';
        } elseif (!in_array($role, $roles, true) || mb_strlen($name) < 2) {
            $notice = 'invalid';
        } else {
            $pdo->prepare('UPDATE admin_staff SET name = ?, role_code = ? WHERE id = ?')->execute([$name, $role, $staffId]);
            admin_audit($pdo, $adminActor, 'staff_role', 'staff', $staffId, $reason, 'role=' . $role);
            $notice = 'applied';
        }
    } elseif ($action === 'password' && $staffId > 0) {
        $password = (string) ($_POST['password'] ?? '');
        if ((int) ($adminActor['staff_id'] ?? 0) === $staffId || strlen($password) < 8) {
            $notice = 'invalid';
        } else {
            $pdo->prepare('UPDATE admin_staff SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $staffId]);
            admin_audit($pdo, $adminActor, 'staff_password_reset', 'staff', $staffId, $reason, '');
            $notice = 'applied';
        }
    } elseif (($action === 'activate' || $action === 'deactivate') && $staffId > 0) {
        if ((int) ($adminActor['staff_id'] ?? 0) === $staffId) {
            $notice = 'self';
        } else {
            $status = $action === 'deactivate' ? 'inactive' : 'active';
            $pdo->prepare('UPDATE admin_staff SET status = ? WHERE id = ? AND role_code <> \'owner\'')->execute([$status, $staffId]);
            admin_audit($pdo, $adminActor, $action === 'deactivate' ? 'staff_disable' : 'staff_enable', 'staff', $staffId, $reason, '');
            $notice = 'applied';
        }
    } else {
        $notice = 'invalid';
    }
}

$params = [];
$where = '';
if ($q !== '') {
    $where = "WHERE (email LIKE ? ESCAPE '\\\\' OR name LIKE ? ESCAPE '\\\\')";
    $like = admin_like(mb_substr($q, 0, 120));
    $params = [$like, $like];
}
$sql = 'SELECT id, email, name, role_code, status, last_login_at, created_at FROM admin_staff ' . $where . ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

admin_start(admin_t('Staff', 'الموظفون'));
if ($notice !== '') {
    echo '<p class="flash">' . admin_h(admin_flash($notice)) . '</p>';
}
echo '<p class="note">' . admin_h(admin_t(
    'Staff accounts are separate from customer users. Only the Owner manages staff here.',
    'حسابات الموظفين منفصلة عن عملاء users. المالك فقط يدير الموظفين هنا.'
)) . '</p>';

admin_toolbar([['href' => '#create-staff', 'label' => admin_t('New staff member', 'موظف جديد'), 'class' => 'primary']]);
admin_panel_open(admin_t('Create staff', 'إنشاء موظف'), admin_t('Add platform admin staff — not customer users.', 'إضافة موظف إدارة المنصة — وليس عملاء.'));
echo '<form id="create-staff" class="stack form-grid" method="post">';
echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="action" value="create">';
echo '<label>' . admin_h(admin_t('Email', 'البريد')) . '<input type="email" name="email" required></label>';
echo '<label>' . admin_h(admin_t('Name', 'الاسم')) . '<input name="name" required></label>';
echo '<label>' . admin_h(admin_t('Role', 'الدور')) . '<select name="role_code">';
foreach ($roles as $role) {
    echo '<option value="' . admin_h($role) . '">' . admin_h($role) . '</option>';
}
echo '</select></label>';
echo '<label>' . admin_h(admin_t('Initial password (8+)', 'كلمة المرور (8+)' )) . '<input type="password" name="password" required minlength="8"></label>';
echo '<p class="full"><button type="submit" class="btn primary">' . admin_h(admin_t('Create', 'إنشاء')) . '</button></p></form>';
admin_panel_close();

echo '<form class="search" method="get"><input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Search staff', 'بحث الموظفين')) . '"><button type="submit">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';

admin_panel_open(admin_t('Staff list', 'قائمة الموظفين'));
$canManage = true;
$formId = admin_bulk_form_open('staff', '/admin/staff.php' . ($q !== '' ? '?q=' . rawurlencode($q) : ''));
admin_bulk_flash_from_query();
admin_crud_toolbar([
    'screen' => 'staff',
    'can_manage' => true,
    'form_id' => $formId,
    'add_href' => '#create-staff',
]);
admin_table_open();
echo '<tr>';
admin_th_select($canManage);
echo '<th>' . admin_h(admin_t('Name', 'الاسم')) . '</th><th>' . admin_h(admin_t('Email', 'البريد')) . '</th><th>' . admin_h(admin_t('Role', 'الدور')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Last login', 'آخر دخول')) . '</th><th>' . admin_h(admin_t('Created', 'أُنشئ')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $inactive = (string) $row['status'] !== 'active';
    $sid = (int) $row['id'];
    $editHref = '#staff-' . $sid;
    echo '<tr id="staff-' . $sid . '">';
    admin_td_select($sid, $editHref, $canManage);
    echo '<td>' . admin_h((string) $row['name']) . '</td><td>' . admin_h((string) $row['email']) . '</td><td>' . admin_h((string) $row['role_code']) . '</td>';
    echo '<td><span class="pill ' . ($inactive ? 'bad' : 'ok') . '">' . admin_h($inactive ? admin_t('Inactive', 'معطّل') : admin_t('Active', 'نشط')) . '</span></td>';
    echo '<td>' . admin_h((string) ($row['last_login_at'] ?? '—')) . '</td><td>' . admin_h((string) $row['created_at']) . '</td><td>';
    admin_crud_row_actions($editHref, $editHref, false, $sid);
    echo '<form class="inline" method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '"><input type="hidden" name="staff_id" value="' . $sid . '">';
    if ($inactive) {
        echo '<input type="hidden" name="action" value="activate"><button type="submit" class="btn small">' . admin_h(admin_t('Activate', 'تفعيل')) . '</button>';
    } else {
        echo '<input type="hidden" name="action" value="deactivate"><button type="submit" class="btn small danger">' . admin_h(admin_t('Disable', 'تعطيل')) . '</button>';
    }
    echo '</form></td></tr>';
}
if ($rows === []) {
    echo '<tr><td colspan="7">' . admin_h(admin_t('No staff accounts yet.', 'لا حسابات موظفين بعد.')) . '</td></tr>';
}
admin_table_close();
admin_panel_close();
admin_bulk_form_close();
admin_end();
