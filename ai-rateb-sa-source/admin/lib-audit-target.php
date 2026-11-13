<?php
declare(strict_types=1);

/**
 * Phase 0: customer audit rows with owner + staff actors.
 * Requires admin/lib.php (admin_table_has) to be loaded first.
 *
 * @return list<array<string,mixed>>
 */
function admin_audit_rows_for_target(object $pdo, string $targetType, int $targetId, int $limit = 50): array
{
    if (!admin_table_has($pdo, 'admin_audit_log', 'id') || $targetId < 1) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $hasStaffCol = admin_table_has($pdo, 'admin_audit_log', 'admin_staff_id');
    $hasStaffTable = admin_table_has($pdo, 'admin_staff', 'id');
    $staffJoin = ($hasStaffCol && $hasStaffTable)
        ? ' LEFT JOIN admin_staff s ON s.id = a.admin_staff_id'
        : '';
    $staffSelect = ($hasStaffCol && $hasStaffTable)
        ? ', a.admin_staff_id, s.email AS staff_email, s.name AS staff_name'
        : ($hasStaffCol ? ', a.admin_staff_id, NULL AS staff_email, NULL AS staff_name' : ', NULL AS admin_staff_id, NULL AS staff_email, NULL AS staff_name');
    $stmt = $pdo->prepare(
        'SELECT a.created_at, a.action, a.target_type, a.target_id, a.reason, a.detail, u.email' . $staffSelect . '
        FROM admin_audit_log a
        LEFT JOIN users u ON u.id = a.admin_user_id' . $staffJoin . '
        WHERE a.target_type = ? AND a.target_id = ?
        ORDER BY a.id DESC
        LIMIT ' . $limit
    );
    $stmt->execute([$targetType, $targetId]);
    return $stmt->fetchAll() ?: [];
}
