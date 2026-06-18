<?php
/**
 * Save manual permissions on a portal role (overrides linked permission role when set).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/portal-roles.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

try {
    $isControl = !empty($_SESSION['control_logged_in']);
    $isAppUser = function_exists('rateb_staff_page_session_ok')
        ? rateb_staff_page_session_ok()
        : (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user());
    if (!$isControl && !$isAppUser) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    if (!$isControl) {
        $isAdmin = isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1;
        $canManage = function_exists('hasPermission') && hasPermission('manage_settings');
        if (!$isAdmin && !$canManage) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $portalRoleId = (int) ($input['portal_role_id'] ?? 0);
    $clearManual = !empty($input['clear_manual']);
    $permissions = isset($input['permissions']) && is_array($input['permissions']) ? $input['permissions'] : [];

    if ($portalRoleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid portal role ID']);
        exit;
    }

    $conn = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
    $useMysqli = $conn instanceof mysqli;
    if (!$useMysqli) {
        if (!class_exists('Database', false)) {
            require_once __DIR__ . '/../../core/bootstrap.php';
        }
        $conn = Database::getInstance()->getConnection();
    }

    rateb_portal_roles_ensure_schema($conn);

    $oldRow = null;
    if ($useMysqli) {
        $stmt = $conn->prepare('SELECT id, name, role_id, permissions FROM portal_roles WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $portalRoleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $oldRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    } else {
        $stmt = $conn->prepare('SELECT id, name, role_id, permissions FROM portal_roles WHERE id = ? LIMIT 1');
        $stmt->execute([$portalRoleId]);
        $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$oldRow) {
        echo json_encode(['success' => false, 'message' => 'Portal role not found']);
        exit;
    }

    $permissionsJson = $clearManual ? null : json_encode(array_values(array_map('strval', $permissions)), JSON_UNESCAPED_UNICODE);

    if ($useMysqli) {
        if ($clearManual) {
            $stmt = $conn->prepare('UPDATE portal_roles SET permissions = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $portalRoleId);
        } else {
            $stmt = $conn->prepare('UPDATE portal_roles SET permissions = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $permissionsJson, $portalRoleId);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        if ($clearManual) {
            $stmt = $conn->prepare('UPDATE portal_roles SET permissions = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$portalRoleId]);
        } else {
            $stmt = $conn->prepare('UPDATE portal_roles SET permissions = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$permissionsJson, $portalRoleId]);
        }
    }

    if (function_exists('rateb_portal_roles_sync_users_for_portal_role')) {
        rateb_portal_roles_sync_users_for_portal_role($conn, $portalRoleId);
    }

    echo json_encode([
        'success' => true,
        'message' => $clearManual
            ? 'Manual permissions cleared — using permission role defaults.'
            : 'Portal role permissions saved.',
        'portal_role_id' => $portalRoleId,
        'permissions_count' => $clearManual ? 0 : count($permissions),
        'has_manual_override' => !$clearManual,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
}
