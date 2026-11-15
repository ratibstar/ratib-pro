<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('roles.manage');
if (!admin_actor_is_owner($adminActor)) {
    http_response_code(403);
    exit(admin_t('Owner only.', 'للمالك فقط.'));
}

ensure_admin_staff_schema($pdo);
$notice = '';
$roles = $pdo->query("SELECT code, name_ar, name_en FROM admin_roles WHERE is_owner = 0 ORDER BY code")->fetchAll();
$perms = $pdo->query('SELECT code, label_ar, label_en FROM admin_permissions ORDER BY code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $roleCode = (string) ($_POST['role_code'] ?? '');
    if ($roleCode === '' || $roleCode === 'owner') {
        $notice = 'invalid';
    } else {
        $chosen = [];
        foreach ($perms as $p) {
            $code = (string) $p['code'];
            if (isset($_POST['perm'][$code])) {
                $chosen[] = $code;
            }
        }
        $pdo->prepare('DELETE FROM admin_role_permissions WHERE role_code = ?')->execute([$roleCode]);
        $ins = $pdo->prepare('INSERT INTO admin_role_permissions (role_code, permission_code) VALUES (?, ?)');
        foreach ($chosen as $code) {
            $ins->execute([$roleCode, $code]);
        }
        admin_audit($pdo, $adminActor, 'roles_update', 'role', 0, 'owner', 'role=' . $roleCode . ';count=' . count($chosen));
        $notice = 'applied';
    }
}

$matrix = [];
$rp = $pdo->query('SELECT role_code, permission_code FROM admin_role_permissions')->fetchAll();
foreach ($rp as $row) {
    $matrix[(string) $row['role_code']][(string) $row['permission_code']] = true;
}

admin_start(admin_t('Roles & permissions', 'الأدوار والصلاحيات'));
admin_toolbar([['href' => '/admin/staff.php', 'label' => admin_t('Staff', 'موظفو المنصة'), 'class' => 'primary']]);
if ($notice !== '') {
    admin_flash_box($notice, $notice === 'applied' ? 'ok' : 'bad');
}
admin_panel_open(admin_t('Permission matrix', 'مصفوفة الصلاحيات'), admin_t(
    'Toggle what each staff role can access. The Owner always has full access. Changes apply on next staff login.',
    'فعّل ما يمكن لكل دور الوصول إليه. المالك دائمًا بصلاحيات كاملة. التغييرات تُطبَّق عند دخول الموظف التالي.'
));
echo '<div class="perm-grid">';
foreach ($roles as $role) {
    $code = (string) $role['code'];
    $name = $adminLang === 'ar' ? (string) $role['name_ar'] : (string) $role['name_en'];
    echo '<div class="perm-role">';
    echo '<h3>' . admin_h($name) . ' <span class="note">(' . admin_h($code) . ')</span></h3>';
    echo '<form method="post" class="stack">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
    echo '<input type="hidden" name="role_code" value="' . admin_h($code) . '">';
    foreach ($perms as $p) {
        $pcode = (string) $p['code'];
        $label = $adminLang === 'ar' ? (string) $p['label_ar'] : (string) $p['label_en'];
        $on = !empty($matrix[$code][$pcode]);
        echo '<label><input type="checkbox" name="perm[' . admin_h($pcode) . ']" value="1"' . ($on ? ' checked' : '') . '> ' . admin_h($label) . '</label>';
    }
    echo '<button type="submit" class="btn primary">' . admin_h(admin_t('Save role', 'حفظ الدور')) . '</button>';
    echo '</form></div>';
}
echo '</div>';
admin_panel_close();
admin_end();
