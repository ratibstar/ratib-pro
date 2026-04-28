<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat-voice/messages.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat-voice/messages.php`.
 */
/**
 * Chat & Voice Service - Messages API
 * Handles message sending, receiving, and management
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
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get messages for a conversation
            getMessages($conn, $userId);
            break;
            
        case 'POST':
            // Send new message
            sendMessage($conn, $userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Chat Voice Messages API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get messages for a conversation
 */
function getMessages($conn, $userId) {
    $conversationId = intval($_GET['conversation_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    if ($conversationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
        return;
    }
    
    // Verify user is participant
    if (!isParticipant($conn, $conversationId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            m.message_id,
            m.conversation_id,
            m.sender_id,
            m.message_text,
            m.message_type,
            m.file_path,
            m.file_name,
            m.file_size,
            m.is_read,
            m.read_at,
            m.created_at,
            m.status,
            COALESCE(u.full_name, u.name, u.username) as sender_name,
            u.username as sender_username
        FROM chat_messages m
        INNER JOIN users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ?
        AND m.status != 'deleted'
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("iii", $conversationId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'message_id' => intval($row['message_id']),
            'conversation_id' => intval($row['conversation_id']),
            'sender_id' => intval($row['sender_id']),
            'sender_name' => htmlspecialchars($row['sender_name'], ENT_QUOTES, 'UTF-8'),
            'sender_username' => htmlspecialchars($row['sender_username'], ENT_QUOTES, 'UTF-8'),
            'message_text' => htmlspecialchars($row['message_text'], ENT_QUOTES, 'UTF-8'),
            'message_type' => $row['message_type'],
            'file_path' => $row['file_path'],
            'file_name' => $row['file_name'],
            'file_size' => $row['file_size'] ? intval($row['file_size']) : null,
            'is_read' => (bool)$row['is_read'],
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at'],
            'status' => $row['status'],
            'is_sent' => ($row['sender_id'] == $userId)
        ];
    }
    
    $stmt->close();
    
    // Reverse to show oldest first
    $messages = array_reverse($messages);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages)
    ]);
}

/**
 * Send new message
 */
function sendMessage($conn, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = intval($input['conversation_id'] ?? 0);
    $messageText = trim($input['message'] ?? '');
    $messageType = $input['message_type'] ?? 'text';
    
    if ($conversationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
        return;
    }
    
    if (empty($messageText) && $messageType === 'text') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message text is required']);
        return;
    }
    
    if (strlen($messageText) > 5000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message is too long (max 5000 characters)']);
        return;
    }
    
    // Verify user is participant
    if (!isParticipant($conn, $conversationId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO chat_messages 
        (conversation_id, sender_id, message_text, message_type, status) 
        VALUES (?, ?, ?, ?, 'sent')
    ");
    
    $stmt->bind_param("iiss", $conversationId, $userId, $messageText, $messageType);
    $stmt->execute();
    $messageId = $conn->insert_id;
    $stmt->close();
    
    // Update conversation last_message_at
    $stmt = $conn->prepare("
        UPDATE chat_conversations 
        SET last_message_at = NOW() 
        WHERE conversation_id = ?
    ");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    $stmt->close();
    
    // Get the created message
    $stmt = $conn->prepare("
        SELECT 
            m.message_id,
            m.conversation_id,
            m.sender_id,
            m.message_text,
            m.message_type,
            m.created_at,
            m.status,
            COALESCE(u.full_name, u.name, u.username) as sender_name,
            u.username as sender_username
        FROM chat_messages m
        INNER JOIN users u ON m.sender_id = u.user_id
        WHERE m.message_id = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();
    $stmt->close();
    
    if ($message) {
        $message['is_sent'] = true;
        $message['message_text'] = htmlspecialchars($message['message_text'], ENT_QUOTES, 'UTF-8');
        $message['sender_name'] = htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8');
        $message['sender_username'] = htmlspecialchars($message['sender_username'], ENT_QUOTES, 'UTF-8');
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'message_text' => 'Message sent successfully'
    ]);
}

/**
 * Check if user is participant in conversation
 */
function isParticipant($conn, $conversationId, $userId) {
    $stmt = $conn->prepare("
        SELECT participant_id 
        FROM chat_conversation_participants 
        WHERE conversation_id = ? 
        AND user_id = ? 
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->bind_param("ii", $conversationId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}
