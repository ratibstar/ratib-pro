<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/messages.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/messages.php`.
 */
/**
 * Messages/Notifications API
 * CRUD operations for accounting messages and notifications
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_messages'");
    if ($tableCheck->num_rows === 0) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Messages table not found. Please run setup first.']);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            // List messages
            $messageId = isset($_GET['id']) ? intval($_GET['id']) : null;
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            $category = $_GET['category'] ?? '';
            $type = $_GET['type'] ?? '';
            $relatedType = $_GET['related_type'] ?? '';
            $relatedId = isset($_GET['related_id']) ? intval($_GET['related_id']) : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            if ($messageId) {
                // Get single message
                $stmt = $conn->prepare("
                    SELECT m.*
                    FROM accounting_messages m
                    WHERE m.id = ? AND (m.user_id IS NULL OR m.user_id = ?)
                ");
                $stmt->bind_param('ii', $messageId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Check if user has read this message
                    $readCheck = $conn->prepare("SELECT read_at FROM accounting_message_reads WHERE message_id = ? AND user_id = ?");
                    $readCheck->bind_param('ii', $messageId, $userId);
                    $readCheck->execute();
                    $readResult = $readCheck->get_result();
                    $row['is_read'] = $readResult->num_rows > 0 ? 1 : 0;
                    
                    echo json_encode(['success' => true, 'message' => $row]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Message not found']);
                }
            } else {
                // List messages for current user
                $where = ["(m.user_id IS NULL OR m.user_id = ?)"];
                $params = [$userId];
                $types = 'i';
                
                if ($unreadOnly) {
                    $where[] = "NOT EXISTS (
                        SELECT 1 FROM accounting_message_reads mr 
                        WHERE mr.message_id = m.id AND mr.user_id = ?
                    )";
                    $params[] = $userId;
                    $types .= 'i';
                }
                
                if ($category) {
                    $where[] = "m.category = ?";
                    $params[] = $category;
                    $types .= 's';
                }
                
                if ($type) {
                    $where[] = "m.type = ?";
                    $params[] = $type;
                    $types .= 's';
                }
                
                if ($relatedType) {
                    $where[] = "m.related_type = ?";
                    $params[] = $relatedType;
                    $types .= 's';
                }
                
                if ($relatedId) {
                    $where[] = "m.related_id = ?";
                    $params[] = $relatedId;
                    $types .= 'i';
                }
                
                // Check for expired messages
                $where[] = "(m.expires_at IS NULL OR m.expires_at > NOW())";
                
                $whereClause = 'WHERE ' . implode(' AND ', $where);
                
                $query = "
                    SELECT m.*,
                           CASE 
                               WHEN EXISTS (
                                   SELECT 1 FROM accounting_message_reads mr 
                                   WHERE mr.message_id = m.id AND mr.user_id = ?
                               ) THEN 1 
                               ELSE 0 
                           END as is_read_by_user
                    FROM accounting_messages m
                    $whereClause
                    ORDER BY m.is_important DESC, m.created_at DESC
                    LIMIT ?
                ";
                
                $params[] = $userId;
                $types .= 'i';
                $params[] = $limit;
                $types .= 'i';
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $messages = [];
                while ($row = $result->fetch_assoc()) {
                    $messages[] = $row;
                }
                
                // Get unread count
                $unreadCountQuery = "
                    SELECT COUNT(*) as count
                    FROM accounting_messages m
                    WHERE (m.user_id IS NULL OR m.user_id = ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM accounting_message_reads mr 
                        WHERE mr.message_id = m.id AND mr.user_id = ?
                    )
                    AND (m.expires_at IS NULL OR m.expires_at > NOW())
                ";
                $unreadStmt = $conn->prepare($unreadCountQuery);
                $unreadStmt->bind_param('ii', $userId, $userId);
                $unreadStmt->execute();
                $unreadResult = $unreadStmt->get_result();
                $unreadCount = $unreadResult->fetch_assoc()['count'];
                
                echo json_encode([
                    'success' => true, 
                    'messages' => $messages, 
                    'count' => count($messages),
                    'unread_count' => $unreadCount
                ]);
            }
            break;
            
        case 'POST':
            // Create message
            $data = json_decode(file_get_contents('php://input'), true);
            
            $type = $data['type'] ?? 'info';
            $category = $data['category'] ?? 'system_notification';
            $title = $data['title'] ?? '';
            $message = $data['message'] ?? '';
            $relatedType = $data['related_type'] ?? null;
            $relatedId = isset($data['related_id']) ? intval($data['related_id']) : null;
            $targetUserId = isset($data['user_id']) ? intval($data['user_id']) : null;
            $isImportant = isset($data['is_important']) ? intval($data['is_important']) : 0;
            $actionUrl = $data['action_url'] ?? null;
            $actionText = $data['action_text'] ?? null;
            $expiresAt = $data['expires_at'] ?? null;
            
            if (empty($title) || empty($message)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Title and message are required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO accounting_messages 
                (type, category, title, message, related_type, related_id, user_id, is_important, action_url, action_text, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('sssssiissss', 
                $type, $category, $title, $message, $relatedType, $relatedId, 
                $targetUserId, $isImportant, $actionUrl, $actionText, $expiresAt
            );
            
            if ($stmt->execute()) {
                $messageId = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Message created successfully',
                    'id' => $messageId
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error creating message: ' . $conn->error]);
            }
            break;
            
        case 'PUT':
            // Mark message as read
            $data = json_decode(file_get_contents('php://input'), true);
            $messageId = isset($data['id']) ? intval($data['id']) : 0;
            $action = $data['action'] ?? 'mark_read';
            
            if (!$messageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Message ID is required']);
                exit;
            }
            
            if ($action === 'mark_read') {
                // Check if already read
                $checkStmt = $conn->prepare("SELECT id FROM accounting_message_reads WHERE message_id = ? AND user_id = ?");
                $checkStmt->bind_param('ii', $messageId, $userId);
                $checkStmt->execute();
                
                if ($checkStmt->get_result()->num_rows === 0) {
                    // Mark as read
                    $insertStmt = $conn->prepare("INSERT INTO accounting_message_reads (message_id, user_id) VALUES (?, ?)");
                    $insertStmt->bind_param('ii', $messageId, $userId);
                    
                    if ($insertStmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Message marked as read']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Error marking message as read']);
                    }
                } else {
                    echo json_encode(['success' => true, 'message' => 'Message already marked as read']);
                }
            } else if ($action === 'mark_all_read') {
                // Mark all messages as read for current user
                $updateStmt = $conn->prepare("
                    INSERT INTO accounting_message_reads (message_id, user_id)
                    SELECT m.id, ?
                    FROM accounting_messages m
                    WHERE (m.user_id IS NULL OR m.user_id = ?)
                    AND NOT EXISTS (
                        SELECT 1 FROM accounting_message_reads mr 
                        WHERE mr.message_id = m.id AND mr.user_id = ?
                    )
                    AND (m.expires_at IS NULL OR m.expires_at > NOW())
                ");
                $updateStmt->bind_param('iii', $userId, $userId, $userId);
                
                if ($updateStmt->execute()) {
                    $affected = $conn->affected_rows;
                    echo json_encode(['success' => true, 'message' => "Marked $affected messages as read"]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error marking messages as read']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
            
        case 'DELETE':
            // Delete message (only if user created it or is admin)
            $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if (!$messageId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Message ID is required']);
                exit;
            }
            
            // For now, allow deletion of user-specific messages
            $stmt = $conn->prepare("DELETE FROM accounting_messages WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $messageId, $userId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error deleting message: ' . $conn->error]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

