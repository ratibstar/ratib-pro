<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/save_role_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/save_role_permissions.php`.
 */
// EN: Role-permission write endpoint (supports both control/admin session contexts).
// AR: نقطة حفظ صلاحيات الأدوار (تدعم سياق جلسة التحكم وجلسة الإدارة).
require_once __DIR__ . '/../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

// EN: Validate actor session, persist permissions, and append history trail.
// AR: التحقق من الجلسة، حفظ الصلاحيات، ثم إضافة سجل تاريخ للتتبع.
try {
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
    if (!$isControl && !$isAppUser) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }

    // EN: Prefer tenant-scoped mysqli connection in single-URL mode; fallback to PDO bootstrap.
    // AR: تفضيل اتصال mysqli المرتبط بالمستأجر في وضع الرابط الواحد مع بديل PDO عند الحاجة.
    // Use app's connection (mysqli) so we're on the correct country DB in single-URL mode
    $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    $useMysqli = $conn instanceof mysqli;

    if (!$useMysqli) {
        if (!class_exists('Database', false)) {
            require_once __DIR__ . '/../../core/bootstrap.php';
        }
        $conn = Database::getInstance()->getConnection();
        if (!$conn || !($conn instanceof PDO)) {
            throw new Exception('Failed to get database connection');
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['role_id']) || !isset($input['permissions'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    $roleId = (int)$input['role_id'];
    $permissions = is_array($input['permissions']) ? $input['permissions'] : [];

    // EN: Update role permissions using active connection driver (mysqli or PDO).
    // AR: تحديث صلاحيات الدور عبر مشغل الاتصال المتاح حالياً (mysqli أو PDO).
    if ($useMysqli) {
        $stmt = $conn->prepare("SELECT role_id, role_name, permissions FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $role = $res ? $res->fetch_assoc() : null;
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
            exit;
        }
        $oldPermissions = [];
        if (!empty($role['permissions'])) {
            $oldPermissions = json_decode($role['permissions'], true) ?: [];
        }
        $permissionsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE roles SET permissions = ? WHERE role_id = ?");
        $stmt->bind_param("si", $permissionsJson, $roleId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT role_id, role_name, permissions FROM roles WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Role not found']);
            exit;
        }
        $oldPermissions = [];
        if (!empty($role['permissions'])) {
            $oldPermissions = json_decode($role['permissions'], true) ?: [];
        }
        $permissionsJson = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE roles SET permissions = ? WHERE role_id = ?");
        $stmt->execute([$permissionsJson, $roleId]);
    }

    // EN: Non-blocking history logging to preserve auditability without breaking save flow.
    // AR: تسجيل تاريخ غير معطّل للحفاظ على التتبع دون التأثير على عملية الحفظ.
    try {
        if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
            require_once __DIR__ . '/../core/global-history-helper.php';
            if (function_exists('logGlobalHistory') && $role) {
                $newRole = $role;
                $newRole['permissions'] = $permissionsJson;
                @logGlobalHistory('roles', $roleId, 'update', 'settings', $role, $newRole);
            }
        }
    } catch (Exception $e) {
        error_log('History logging failed: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Permissions saved successfully',
        'role_id' => $roleId,
        'permissions_count' => count($permissions)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
