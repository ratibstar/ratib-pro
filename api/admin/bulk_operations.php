<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/bulk_operations.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/bulk_operations.php`.
 */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Database connection using config constants
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['action']) || !isset($data['user_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$action = $data['action'];
$userIds = is_array($data['user_ids']) ? $data['user_ids'] : [$data['user_ids']];

if (empty($userIds)) {
    echo json_encode(['success' => false, 'message' => 'No user IDs provided']);
    exit;
}

try {
    // Load history helper
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    $hasHistoryHelper = file_exists($helperPath);
    if ($hasHistoryHelper) {
        require_once $helperPath;
    }
    
    // Get old data for history
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $oldDataSql = "SELECT * FROM users WHERE user_id IN ($placeholders)";
    $oldStmt = $pdo->prepare($oldDataSql);
    $oldStmt->execute($userIds);
    $oldUsers = $oldStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->beginTransaction();
    
    switch($action) {
        case 'activate':
            $sql = "UPDATE users SET status = 'active' WHERE user_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($userIds);
            $message = 'Users activated successfully';
            
            // Get new data and log history
            if ($hasHistoryHelper && function_exists('logGlobalHistory')) {
                $newStmt = $pdo->prepare($oldDataSql);
                $newStmt->execute($userIds);
                $newUsers = $newStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($userIds as $userId) {
                    $oldUser = null;
                    $newUser = null;
                    foreach ($oldUsers as $u) {
                        if ($u['user_id'] == $userId) {
                            $oldUser = $u;
                            break;
                        }
                    }
                    foreach ($newUsers as $u) {
                        if ($u['user_id'] == $userId) {
                            $newUser = $u;
                            break;
                        }
                    }
                    if ($oldUser && $newUser) {
                        @logGlobalHistory('users', $userId, 'update', 'settings', $oldUser, $newUser);
                    }
                }
            }
            break;
            
        case 'deactivate':
            $sql = "UPDATE users SET status = 'inactive' WHERE user_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($userIds);
            $message = 'Users deactivated successfully';
            
            // Get new data and log history
            if ($hasHistoryHelper && function_exists('logGlobalHistory')) {
                $newStmt = $pdo->prepare($oldDataSql);
                $newStmt->execute($userIds);
                $newUsers = $newStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($userIds as $userId) {
                    $oldUser = null;
                    $newUser = null;
                    foreach ($oldUsers as $u) {
                        if ($u['user_id'] == $userId) {
                            $oldUser = $u;
                            break;
                        }
                    }
                    foreach ($newUsers as $u) {
                        if ($u['user_id'] == $userId) {
                            $newUser = $u;
                            break;
                        }
                    }
                    if ($oldUser && $newUser) {
                        @logGlobalHistory('users', $userId, 'update', 'settings', $oldUser, $newUser);
                    }
                }
            }
            break;
            
        case 'delete':
            $sql = "DELETE FROM users WHERE user_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($userIds);
            $message = 'Users deleted successfully';
            
            // Log history for deleted users
            if ($hasHistoryHelper && function_exists('logGlobalHistory')) {
                foreach ($oldUsers as $oldUser) {
                    @logGlobalHistory('users', $oldUser['user_id'], 'delete', 'settings', $oldUser, null);
                }
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message, 'affected_users' => count($userIds)]);
    
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
}
?>
