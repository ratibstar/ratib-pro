<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat/webhook.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat/webhook.php`.
 */
/**
 * WhatsApp Webhook - Receive messages from WhatsApp
 * This endpoint receives incoming WhatsApp messages and can process them
 */

header('Content-Type: application/json');

require_once '../../includes/config.php';

// Log incoming webhook (for debugging)
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input')
];

error_log("WhatsApp Webhook received: " . json_encode($logData));

// Handle Twilio webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Twilio sends form data, not JSON
    if (empty($input)) {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    // Process incoming WhatsApp message
    if (isset($input['From']) && isset($input['Body'])) {
        $from = $input['From']; // whatsapp:+966599863868
        $message = $input['Body'];
        $messageId = $input['MessageSid'] ?? '';
        
        // Store incoming message
        try {
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
                $conn = $GLOBALS['conn'];
                
                // Create whatsapp_messages table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS whatsapp_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    from_number VARCHAR(50),
                    message TEXT,
                    message_id VARCHAR(100),
                    direction VARCHAR(20) DEFAULT 'inbound',
                    processed TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_from_number (from_number),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $conn->query($createTable);
                
                // Insert message
                $stmt = $conn->prepare("INSERT INTO whatsapp_messages (from_number, message, message_id, direction) VALUES (?, ?, ?, 'inbound')");
                $stmt->bind_param("sss", $from, $message, $messageId);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("WhatsApp webhook error: " . $e->getMessage());
        }
    }
    
    // Respond to Twilio (required)
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response></Response>';
    exit;
}

// Return 200 OK for GET requests (webhook verification)
http_response_code(200);
echo json_encode(['status' => 'ok']);
