<?php
/**
 * EN: Handles API endpoint/business logic in `api/permissions/save_user_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/permissions/save_user_permissions.php`.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/permissions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }

    // Use app's connection (mysqli) so we're on the correct country DB in single-URL mode
    $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    $useMysqli = $conn instanceof mysqli;

    if (!$useMysqli) {
        // Fallback: PDO via api/core/Database if available
        if (!class_exists('Database', false)) {
            $apiDb = __DIR__ . '/../core/Database.php';
            if (file_exists($apiDb)) {
                require_once $apiDb;
            } else {
                require_once __DIR__ . '/../../core/bootstrap.php';
            }
        }
        if (method_exists('Database', 'getInstance')) {
            $conn = Database::getInstance()->getConnection();
        }
        if (!$conn || !($conn instanceof PDO)) {
            throw new Exception('Failed to get database connection');
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['user_id']) || !isset($input['permissions'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    $userId = (int)$input['user_id'];
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    $permissions = is_array($input['permissions']) ? $input['permissions'] : [];
    foreach ($permissions as $perm) {
        if (!is_string($perm)) {
            echo json_encode(['success' => false, 'message' => 'Invalid permission format']);
            exit;
        }
    }

    if ($useMysqli) {
        $columnExists = false;
        $chk = @$conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
        if ($chk && $chk->num_rows > 0) {
            $columnExists = true;
        }
        if (!$columnExists) {
            $conn->query("ALTER TABLE users ADD COLUMN permissions JSON NULL COMMENT 'User-specific permissions (overrides role permissions when set)'");
            $columnExists = true;
        }
        $userPk = 'user_id';
        $chkPk = @$conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
        if (!$chkPk || $chkPk->num_rows === 0) {
            $chkPk = @$conn->query("SHOW COLUMNS FROM users LIKE 'id'");
            if ($chkPk && $chkPk->num_rows > 0) {
                $userPk = 'id';
            }
        }
        $selectFields = $columnExists ? "{$userPk}, username, permissions" : "{$userPk}, username";
        $stmt = $conn->prepare("SELECT {$selectFields} FROM users WHERE {$userPk} = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        $oldPermissions = [];
        if ($columnExists && isset($user['permissions']) && $user['permissions']) {
            $oldPermissions = json_decode($user['permissions'], true) ?: [];
        }
        $permissionsJson = empty($permissions) ? null : json_encode($permissions, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE {$userPk} = ?");
        $stmt->bind_param("si", $permissionsJson, $userId);
        $stmt->execute();
        $rowsUpdated = $stmt->affected_rows;
        // MySQL reports 0 rows when the new JSON equals the old; re-check so the UI does not show a false failure.
        if ($rowsUpdated === 0) {
            $v = $conn->prepare("SELECT permissions FROM users WHERE {$userPk} = ?");
            $v->bind_param("i", $userId);
            $v->execute();
            $vr = $v->get_result();
            $afterRow = $vr ? $vr->fetch_assoc() : null;
            $v->close();
            $afterList = [];
            if ($afterRow && isset($afterRow['permissions']) && $afterRow['permissions'] !== null && $afterRow['permissions'] !== '') {
                $afterList = json_decode($afterRow['permissions'], true) ?: [];
            }
            $norm = static function ($list) {
                if (!is_array($list)) {
                    return [];
                }
                $a = array_map('strval', $list);
                sort($a);
                return $a;
            };
            if ($norm($afterList) === $norm($permissions)) {
                $rowsUpdated = 1;
            }
        }
        $saveDbName = null;
        $dbRes = @$conn->query('SELECT DATABASE()');
        if ($dbRes && ($row = $dbRes->fetch_row())) {
            $saveDbName = $row[0];
        }
    } else {
        $columnExists = false;
        $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
        if ($checkStmt) {
            $rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            $columnExists = count($rows) > 0;
        }
        if (!$columnExists) {
            $conn->exec("ALTER TABLE users ADD COLUMN permissions JSON NULL COMMENT 'User-specific permissions (overrides role permissions when set)'");
            $columnExists = true;
        }
        $pdoPk = ratib_users_primary_key_column($conn);
        $selectFields = $columnExists ? "`{$pdoPk}`, username, permissions" : "`{$pdoPk}`, username";
        $stmt = $conn->prepare("SELECT {$selectFields} FROM users WHERE `{$pdoPk}` = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        $oldPermissions = [];
        if ($columnExists && isset($user['permissions']) && $user['permissions']) {
            $oldPermissions = json_decode($user['permissions'], true) ?: [];
        }
        $permissionsJson = empty($permissions) ? null : json_encode($permissions, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE `{$pdoPk}` = ?");
        $stmt->execute([$permissionsJson, $userId]);
        $rowsUpdated = $stmt->rowCount();
        if ($rowsUpdated === 0) {
            $v = $conn->prepare("SELECT permissions FROM users WHERE `{$pdoPk}` = ?");
            $v->execute([$userId]);
            $afterRow = $v->fetch(PDO::FETCH_ASSOC);
            $afterList = [];
            if ($afterRow && isset($afterRow['permissions']) && $afterRow['permissions'] !== null && $afterRow['permissions'] !== '') {
                $afterList = json_decode($afterRow['permissions'], true) ?: [];
            }
            $norm = static function ($list) {
                if (!is_array($list)) {
                    return [];
                }
                $a = array_map('strval', $list);
                sort($a);
                return $a;
            };
            if ($norm($afterList) === $norm($permissions)) {
                $rowsUpdated = 1;
            }
        }
        $saveDbName = null;
        try {
            $r = $conn->query('SELECT DATABASE()');
            if ($r && ($row = $r->fetch(PDO::FETCH_NUM))) {
                $saveDbName = $row[0];
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    if (!isset($rowsUpdated)) {
        $rowsUpdated = -1;
    }
    if (!isset($saveDbName)) {
        $saveDbName = null;
    }

    try {
        if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
            require_once __DIR__ . '/../core/global-history-helper.php';
            if (function_exists('logGlobalHistory')) {
                logGlobalHistory('users', $userId, 'permissions_updated', 'settings', $oldPermissions, [
                    'permissions' => $permissions,
                    'username' => $user['username']
                ]);
            } elseif (function_exists('logHistory')) {
                logHistory('users', $userId, 'permissions_updated', [
                    'old_permissions' => $oldPermissions,
                    'new_permissions' => $permissions
                ], ['username' => $user['username']]);
            }
        }
    } catch (Exception $e) {
        error_log('History logging failed: ' . $e->getMessage());
    }

    $payload = [
        'success' => true,
        'message' => empty($permissions) ? 'User permissions cleared. User will use role permissions.' : 'User permissions saved successfully',
        'user_id' => $userId,
        'permissions_count' => count($permissions),
        'saved_permissions' => $permissions,
        'rows_updated' => $rowsUpdated,
        'db_name' => $saveDbName,
    ];
    if ($rowsUpdated === 0) {
        $payload['warning'] = 'UPDATE ran but 0 rows affected. User may not exist in this database, or value was unchanged.';
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);

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
