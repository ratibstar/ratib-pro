<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('operations.view');

admin_start(admin_t('Audit log', 'سجل التدقيق'));
admin_crud_toolbar([
    'screen' => 'audit',
    'can_manage' => false,
    'links' => [
        ['href' => '/admin/settings.php', 'label' => admin_t('System', 'النظام'), 'class' => 'quiet'],
    ],
]);
if (!admin_table_has($pdo, 'admin_audit_log', 'id')) {
    echo '<p class="note">' . admin_h(admin_t('The audit table is not available.', 'جدول التدقيق غير متاح.')) . '</p>';
    admin_end();
    exit;
}
$page = admin_page();
$total = (int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn();
$hasStaffCol = admin_table_has($pdo, 'admin_audit_log', 'admin_staff_id');
$hasStaffTable = admin_table_has($pdo, 'admin_staff', 'id');
$staffJoin = ($hasStaffCol && $hasStaffTable)
    ? ' LEFT JOIN admin_staff s ON s.id = a.admin_staff_id'
    : '';
$staffSelect = ($hasStaffCol && $hasStaffTable)
    ? ', a.admin_staff_id, s.email AS staff_email, s.name AS staff_name'
    : ($hasStaffCol ? ', a.admin_staff_id, NULL AS staff_email, NULL AS staff_name' : ', NULL AS admin_staff_id, NULL AS staff_email, NULL AS staff_name');
$rows = $pdo->query(
    'SELECT a.created_at, a.action, a.target_type, a.target_id, a.reason, a.detail, u.email' . $staffSelect . '
    FROM admin_audit_log a LEFT JOIN users u ON u.id = a.admin_user_id' . $staffJoin . '
    ORDER BY a.id DESC LIMIT ' . admin_per_page() . ' OFFSET ' . admin_offset($page)
)->fetchAll();
$missingStaff = admin_t('Staff (removed)', 'موظف (محذوف)');
admin_panel_open(admin_t('Recent events', 'الأحداث الأخيرة'), admin_t('Immutable trail of admin actions on customers, credits, and staff.', 'سجل ثابت لإجراءات الإدارة على العملاء والنقاط والموظفين.'));
admin_table_open();
echo '<tr><th>' . admin_h(admin_t('When', 'الوقت')) . '</th><th>' . admin_h(admin_t('Actor', 'الفاعل')) . '</th><th>' . admin_h(admin_t('Action', 'الإجراء')) . '</th><th>' . admin_h(admin_t('Target', 'الهدف')) . '</th><th>' . admin_h(admin_t('Reason', 'السبب')) . '</th></tr>';
foreach ($rows as $row) {
    $actorLabel = admin_audit_actor_display($row, $missingStaff);
    echo '<tr><td>' . admin_h((string) $row['created_at']) . '</td><td>' . admin_h($actorLabel) . '</td><td>' . admin_h((string) $row['action']) . '</td><td>' . admin_h((string) $row['target_type'] . ' ' . (string) ($row['target_id'] ?? '')) . '</td><td>' . admin_h((string) $row['reason']) . '</td></tr>';
}
if ($rows === []) {
    admin_table_empty_row(5, admin_t('No audit events yet.', 'لا أحداث تدقيق بعد.'));
}
admin_table_close();
admin_pager($page, $total, '/admin/audit.php?page=');
admin_panel_close();
admin_end();