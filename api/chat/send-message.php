<?php
/**
 * EN: Handles API endpoint/business logic in `api/chat/send-message.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/chat/send-message.php`.
 */
/**
 * Chat Widget API - Send Message to WhatsApp
 * Receives chat messages from frontend and forwards to WhatsApp
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';
$userName = isset($input['userName']) ? trim($input['userName']) : 'Guest';
$userEmail = isset($input['userEmail']) ? trim($input['userEmail']) : '';
$userPhone = isset($input['userPhone']) ? trim($input['userPhone']) : '';

// Validate message
if (empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Message is required'
    ]);
    exit;
}

// Load WhatsApp configuration (if config file exists)
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    $chatConfig = require $configFile;
} else {
    // Default configuration - UPDATE THESE VALUES
    $chatConfig = [
        'enabled' => true,
        'phone_number' => '+966599863868',
        'whatsapp_business_access_token' => '', // TODO: Get from Meta Business
        'whatsapp_business_phone_id' => '', // TODO: Get from Meta Business
        'twilio' => [
            'account_sid' => '',
            'auth_token' => '',
            'whatsapp_from' => 'whatsapp:+14155238886',
        ],
        'webhook_url' => SITE_URL . '/api/chat/webhook.php'
    ];
}

// WhatsApp Configuration
$whatsappConfig = [
    'enabled' => $chatConfig['enabled'] ?? true,
    'phone_number' => $chatConfig['phone_number'] ?? '+966599863868',
    // WhatsApp Business Cloud API (Meta - Primary method)
    'whatsapp_business_access_token' => $chatConfig['whatsapp_business_access_token'] ?? '',
    'whatsapp_business_phone_id' => $chatConfig['whatsapp_business_phone_id'] ?? '',
    'whatsapp_business_api_version' => $chatConfig['whatsapp_business_api_version'] ?? 'v18.0',
    // Twilio (Fallback option)
    'twilio_account_sid' => $chatConfig['twilio']['account_sid'] ?? '',
    'twilio_auth_token' => $chatConfig['twilio']['auth_token'] ?? '',
    'twilio_whatsapp_from' => $chatConfig['twilio']['whatsapp_from'] ?? 'whatsapp:+14155238886',
    'webhook_url' => $chatConfig['webhook_url'] ?? SITE_URL . '/api/chat/webhook.php'
];

// Function to send WhatsApp message via WhatsApp Business Cloud API (Meta - Official)
function sendWhatsAppMessage($to, $message, $config) {
    if (!$config['enabled']) {
        return ['success' => false, 'message' => 'WhatsApp integration is disabled'];
    }

    // Use WhatsApp Business Cloud API (Meta's official API)
    if (!empty($config['whatsapp_business_access_token']) && !empty($config['whatsapp_business_phone_id'])) {
        return sendViaWhatsAppBusinessAPI($to, $message, $config);
    }
    
    // Fallback: Use Twilio if configured
    if (!empty($config['twilio_account_sid']) && !empty($config['twilio_auth_token'])) {
        return sendViaTwilio($to, $message, $config);
    }

    return ['success' => false, 'message' => 'WhatsApp credentials not configured'];
}

// Send via WhatsApp Business Cloud API (Meta - Official, Free)
function sendViaWhatsAppBusinessAPI($to, $message, $config) {
    $phoneNumberId = $config['whatsapp_business_phone_id'];
    $accessToken = $config['whatsapp_business_access_token'];
    $version = $config['whatsapp_business_api_version'] ?? 'v18.0';
    
    // Remove + and any spaces from phone number
    $to = preg_replace('/[^0-9]/', '', $to);
    
    $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => [
            'body' => $message
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'Message sent successfully via WhatsApp Business API',
            'message_id' => $result['messages'][0]['id'] ?? null
        ];
    } else {
        $error = json_decode($response, true);
        return [
            'success' => false,
            'message' => $error['error']['message'] ?? 'Failed to send WhatsApp message',
            'code' => $httpCode,
            'error' => $error
        ];
    }
}

// Send via Twilio (fallback option)
function sendViaTwilio($to, $message, $config) {
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$config['twilio_account_sid']}/Messages.json";
    
    $data = [
        'From' => $config['twilio_whatsapp_from'],
        'To' => 'whatsapp:' . $to,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, $config['twilio_account_sid'] . ':' . $config['twilio_auth_token']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'Message sent successfully',
            'sid' => $result['sid'] ?? null
        ];
    } else {
        $error = json_decode($response, true);
        return [
            'success' => false,
            'message' => $error['message'] ?? 'Failed to send WhatsApp message',
            'code' => $httpCode
        ];
    }
}

// Function to send WhatsApp message via cURL (Alternative method - no Twilio)
function sendWhatsAppMessageDirect($to, $message, $config) {
    // Alternative: Use WhatsApp Business API directly or other service
    // This is a placeholder - you can integrate with other WhatsApp APIs
    
    // Example: Using a WhatsApp API service
    // You can use services like:
    // - WhatsApp Business API (official)
    // - ChatAPI
    // - Wati.io
    // - 360dialog
    
    return ['success' => false, 'message' => 'Direct WhatsApp API not configured'];
}

// Format message for WhatsApp
$whatsappMessage = "📱 *New Chat Message from Website*\n\n";
$whatsappMessage .= "*From:* " . ($userName !== 'Guest' ? $userName : 'Anonymous') . "\n";
if (!empty($userEmail)) {
    $whatsappMessage .= "*Email:* " . $userEmail . "\n";
}
if (!empty($userPhone)) {
    $whatsappMessage .= "*Phone:* " . $userPhone . "\n";
}
$whatsappMessage .= "*Time:* " . date('Y-m-d H:i:s') . "\n\n";
$whatsappMessage .= "*Message:*\n" . $message;

// Use WhatsApp Business Cloud API (Meta) - Direct to phone, NO QR code needed!
// This sends messages directly to your WhatsApp number without any QR scanning
$result = sendWhatsAppMessage($whatsappConfig['phone_number'], $whatsappMessage, $whatsappConfig);

// Log the message to database (optional)
try {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
        $conn = $GLOBALS['conn'];
        
        // Create chat_messages table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(255),
            user_email VARCHAR(255),
            user_phone VARCHAR(50),
            message TEXT,
            whatsapp_sent TINYINT(1) DEFAULT 0,
            whatsapp_response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->query($createTable);
        
        // Insert message
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_name, user_email, user_phone, message, whatsapp_sent, whatsapp_response) VALUES (?, ?, ?, ?, ?, ?)");
        $whatsappSent = $result['success'] ? 1 : 0;
        $whatsappResponse = json_encode($result);
        $stmt->bind_param("ssssis", $userName, $userEmail, $userPhone, $message, $whatsappSent, $whatsappResponse);
        $stmt->execute();
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Chat message logging error: " . $e->getMessage());
}

// Return response
if ($result['success']) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Message sent to WhatsApp successfully',
        'data' => $result
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Failed to send message',
        'error' => $result
    ]);
}
