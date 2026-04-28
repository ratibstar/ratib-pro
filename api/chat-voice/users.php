<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat-voice/users.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat-voice/users.php`.
 */
/**
 * Chat & Voice Service - Users API
 * Handles user listing and search for conversations
 */

require_once '../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get database connection
if (!isset($conn) || $conn === null) {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
        $conn = $GLOBALS['conn'];
    } else {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $conn->set_charset("utf8mb4");
            $GLOBALS['conn'] = $conn;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
}

$userId = intval($_SESSION['user_id']);
$agencyId = intval($_SESSION['agency_id'] ?? 1);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get users list
            $search = $_GET['q'] ?? '';
            $searchTerm = trim($search);
            
            if (!empty($searchTerm)) {
                searchUsers($conn, $userId, $agencyId, $searchTerm);
            } else {
                getUsers($conn, $userId, $agencyId);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Chat Voice Users API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all users in the same agency
 */
function getUsers($conn, $currentUserId, $agencyId) {
    $users = [];
    
    try {
        // Check if agency_id column exists in users table
        $checkResult = $conn->query("SHOW COLUMNS FROM users LIKE 'agency_id'");
        $hasAgencyId = $checkResult && $checkResult->num_rows > 0;
        
        if ($hasAgencyId) {
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    COALESCE(u.full_name, u.name, u.username) as display_name,
                    u.email,
                    COALESCE(up.status, 'offline') as online_status,
                    COALESCE(up.last_seen, NULL) as last_seen
                FROM users u
                LEFT JOIN user_presence up ON u.user_id = up.user_id
                WHERE u.status = 'active'
                AND u.user_id != ?
                AND (u.agency_id IS NULL OR u.agency_id = ?)
                ORDER BY up.status DESC, up.last_seen DESC, u.username ASC
                LIMIT 100
            ");
            $stmt->bind_param("ii", $currentUserId, $agencyId);
        } else {
            // Fallback: query without agency_id
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    COALESCE(u.full_name, u.name, u.username) as display_name,
                    u.email,
                    COALESCE(up.status, 'offline') as online_status,
                    COALESCE(up.last_seen, NULL) as last_seen
                FROM users u
                LEFT JOIN user_presence up ON u.user_id = up.user_id
                WHERE u.status = 'active'
                AND u.user_id != ?
                ORDER BY up.status DESC, up.last_seen DESC, u.username ASC
                LIMIT 100
            ");
            $stmt->bind_param("i", $currentUserId);
        }
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'user_id' => intval($row['user_id']),
                'username' => htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'),
                'display_name' => htmlspecialchars($row['display_name'], ENT_QUOTES, 'UTF-8'),
                'email' => htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'),
                'online_status' => $row['online_status'] ?? 'offline',
                'last_seen' => $row['last_seen']
            ];
        }
        
        $stmt->close();
        
        // Always return success, even with empty array
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (Exception $e) {
        error_log("getUsers Error: " . $e->getMessage());
        error_log("User ID: " . $currentUserId . ", Agency ID: " . $agencyId);
        if (isset($stmt)) {
            $stmt->close();
        }
        throw $e; // Re-throw to be caught by outer try-catch
    }
}

/**
 * Search users
 */
function searchUsers($conn, $currentUserId, $agencyId, $searchTerm) {
    $users = [];
    $searchPattern = '%' . $conn->real_escape_string($searchTerm) . '%';
    
    try {
        // Check if agency_id column exists in users table
        $checkResult = $conn->query("SHOW COLUMNS FROM users LIKE 'agency_id'");
        $hasAgencyId = $checkResult && $checkResult->num_rows > 0;
        
        if ($hasAgencyId) {
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    COALESCE(u.full_name, u.name, u.username) as display_name,
                    u.email,
                    COALESCE(up.status, 'offline') as online_status,
                    COALESCE(up.last_seen, NULL) as last_seen
                FROM users u
                LEFT JOIN user_presence up ON u.user_id = up.user_id
                WHERE u.status = 'active'
                AND u.user_id != ?
                AND (u.agency_id IS NULL OR u.agency_id = ?)
                AND (
                    u.username LIKE ? 
                    OR u.full_name LIKE ? 
                    OR u.name LIKE ? 
                    OR u.email LIKE ?
                )
                ORDER BY up.status DESC, u.username ASC
                LIMIT 50
            ");
            $stmt->bind_param("iissss", $currentUserId, $agencyId, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
        } else {
            // Fallback: query without agency_id
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    COALESCE(u.full_name, u.name, u.username) as display_name,
                    u.email,
                    COALESCE(up.status, 'offline') as online_status,
                    COALESCE(up.last_seen, NULL) as last_seen
                FROM users u
                LEFT JOIN user_presence up ON u.user_id = up.user_id
                WHERE u.status = 'active'
                AND u.user_id != ?
                AND (
                    u.username LIKE ? 
                    OR u.full_name LIKE ? 
                    OR u.name LIKE ? 
                    OR u.email LIKE ?
                )
                ORDER BY up.status DESC, u.username ASC
                LIMIT 50
            ");
            $stmt->bind_param("issss", $currentUserId, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
        }
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'user_id' => intval($row['user_id']),
                'username' => htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'),
                'display_name' => htmlspecialchars($row['display_name'], ENT_QUOTES, 'UTF-8'),
                'email' => htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'),
                'online_status' => $row['online_status'] ?? 'offline',
                'last_seen' => $row['last_seen']
            ];
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        
    } catch (Exception $e) {
        error_log("searchUsers Error: " . $e->getMessage());
        if (isset($stmt)) {
            $stmt->close();
        }
        throw $e; // Re-throw to be caught by outer try-catch
    }
}
