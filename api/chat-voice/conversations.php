<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat-voice/conversations.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat-voice/conversations.php`.
 */
/**
 * Chat & Voice Service - Conversations API
 * Handles conversation listing, creation, and management
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
            // Get all conversations for the user
            getConversations($conn, $userId, $agencyId);
            break;
            
        case 'POST':
            // Create new conversation
            createConversation($conn, $userId, $agencyId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Chat Voice API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get all conversations for the user
 */
function getConversations($conn, $userId, $agencyId) {
    $conversations = [];
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.conversation_id,
                c.conversation_type,
                c.title,
                c.last_message_at,
                c.created_at,
                (
                    SELECT m.message_text 
                    FROM chat_messages m 
                    WHERE m.conversation_id = c.conversation_id 
                    ORDER BY m.created_at DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT COUNT(*) 
                    FROM chat_messages m 
                    WHERE m.conversation_id = c.conversation_id 
                    AND m.sender_id != ? 
                    AND (m.is_read = 0 OR m.is_read IS NULL OR m.read_at IS NULL OR m.read_at = '0000-00-00 00:00:00')
                ) as unread_count
            FROM chat_conversations c
            INNER JOIN chat_conversation_participants cp ON c.conversation_id = cp.conversation_id
            WHERE cp.user_id = ? 
            AND cp.status = 'active'
            AND c.status = 'active'
            AND (c.agency_id IS NULL OR c.agency_id = ?)
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
            LIMIT 100
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("iii", $userId, $userId, $agencyId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Get participants for each conversation
            $participants = getConversationParticipants($conn, $row['conversation_id'], $userId);
            $row['participants'] = $participants;
            $row['unread_count'] = intval($row['unread_count'] ?? 0);
            $conversations[] = $row;
        }
        
        $stmt->close();
        
        // Always return success, even with empty array
        echo json_encode([
            'success' => true,
            'conversations' => $conversations,
            'count' => count($conversations)
        ]);
        
    } catch (Exception $e) {
        error_log("getConversations Error: " . $e->getMessage());
        error_log("User ID: " . $userId . ", Agency ID: " . $agencyId);
        if (isset($stmt)) {
            $stmt->close();
        }
        throw $e; // Re-throw to be caught by outer try-catch
    }
}

/**
 * Get conversation participants
 */
function getConversationParticipants($conn, $conversationId, $currentUserId) {
    $participants = [];
    
    $stmt = $conn->prepare("
        SELECT 
            cp.user_id,
            u.username,
            COALESCE(u.full_name, u.name, u.username) as display_name,
            u.email,
            COALESCE(up.status, 'offline') as online_status
        FROM chat_conversation_participants cp
        INNER JOIN users u ON cp.user_id = u.user_id
        LEFT JOIN user_presence up ON u.user_id = up.user_id
        WHERE cp.conversation_id = ? 
        AND cp.status = 'active'
        AND u.status = 'active'
        ORDER BY cp.user_id = ? DESC, u.username ASC
    ");
    
    $stmt->bind_param("ii", $conversationId, $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
    
    $stmt->close();
    return $participants;
}

/**
 * Create new conversation
 */
function createConversation($conn, $userId, $agencyId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $participantIds = $input['participant_ids'] ?? [];
    $title = $input['title'] ?? null;
    
    if (empty($participantIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'At least one participant is required']);
        return;
    }
    
    // Add current user to participants if not included
    if (!in_array($userId, $participantIds)) {
        $participantIds[] = $userId;
    }
    
    // Remove duplicates
    $participantIds = array_unique(array_map('intval', $participantIds));
    
    // Check if direct conversation already exists between two users
    if (count($participantIds) === 2) {
        $existingConv = findExistingDirectConversation($conn, $participantIds[0], $participantIds[1]);
        if ($existingConv) {
            echo json_encode([
                'success' => true,
                'conversation' => $existingConv,
                'message' => 'Conversation already exists'
            ]);
            return;
        }
    }
    
    $conn->begin_transaction();
    
    try {
        // Create conversation
        $stmt = $conn->prepare("
            INSERT INTO chat_conversations 
            (conversation_type, agency_id, created_by, title, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        
        $conversationType = count($participantIds) > 2 ? 'group' : 'direct';
        $stmt->bind_param("siis", $conversationType, $agencyId, $userId, $title);
        $stmt->execute();
        $conversationId = $conn->insert_id;
        $stmt->close();
        
        // Add participants
        $stmt = $conn->prepare("
            INSERT INTO chat_conversation_participants 
            (conversation_id, user_id, status) 
            VALUES (?, ?, 'active')
        ");
        
        foreach ($participantIds as $participantId) {
            $stmt->bind_param("ii", $conversationId, $participantId);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        
        // Get created conversation
        $conversation = getConversationById($conn, $conversationId, $userId);
        
        echo json_encode([
            'success' => true,
            'conversation' => $conversation,
            'message' => 'Conversation created successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Find existing direct conversation between two users
 */
function findExistingDirectConversation($conn, $userId1, $userId2) {
    $stmt = $conn->prepare("
        SELECT c.conversation_id
        FROM chat_conversations c
        INNER JOIN chat_conversation_participants cp1 ON c.conversation_id = cp1.conversation_id
        INNER JOIN chat_conversation_participants cp2 ON c.conversation_id = cp2.conversation_id
        WHERE cp1.user_id = ? 
        AND cp2.user_id = ?
        AND c.conversation_type = 'direct'
        AND c.status = 'active'
        AND cp1.status = 'active'
        AND cp2.status = 'active'
        LIMIT 1
    ");
    
    $stmt->bind_param("ii", $userId1, $userId2);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        return getConversationById($conn, $row['conversation_id'], $userId1);
    }
    
    return null;
}

/**
 * Get conversation by ID
 */
function getConversationById($conn, $conversationId, $userId) {
    $stmt = $conn->prepare("
        SELECT 
            c.conversation_id,
            c.conversation_type,
            c.title,
            c.last_message_at,
            c.created_at
        FROM chat_conversations c
        INNER JOIN chat_conversation_participants cp ON c.conversation_id = cp.conversation_id
        WHERE c.conversation_id = ? 
        AND cp.user_id = ?
        AND c.status = 'active'
        LIMIT 1
    ");
    
    $stmt->bind_param("ii", $conversationId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversation = $result->fetch_assoc();
    $stmt->close();
    
    if ($conversation) {
        $conversation['participants'] = getConversationParticipants($conn, $conversationId, $userId);
    }
    
    return $conversation;
}
