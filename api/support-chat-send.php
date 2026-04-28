<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-send.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-send.php`.
 */
/**
 * Public API: User sends message to escalated support chat (control panel storage)
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_chat_db.php';
mysqli_report(MYSQLI_REPORT_OFF);

function jsonOut($data) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"success":false,"message":"Server error"}';
    }
    echo $json;
    exit;
}

$conn = ratib_support_chat_db();
if (!$conn) {
    jsonOut(['success' => false, 'message' => 'Support chat is not configured']);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$chatId = (int)($input['chat_id'] ?? 0);
$chatToken = substr(trim((string)($input['chat_token'] ?? '')), 0, 64);
$message = trim((string)($input['message'] ?? ''));
$message = substr($message, 0, 8000);

if ($chatId <= 0 || $chatToken === '' || $message === '') {
    jsonOut(['success' => false, 'message' => 'Invalid request']);
}

$stmt = $conn->prepare('SELECT id FROM control_support_chats WHERE id = ? AND chat_token = ? AND status = \'open\'');
if (!$stmt) {
    jsonOut(['success' => false, 'message' => 'Database error']);
}
$stmt->bind_param('is', $chatId, $chatToken);
$stmt->execute();
$stmt->store_result();
$ok = $stmt->num_rows > 0;
$stmt->close();

if (!$ok) {
    jsonOut(['success' => false, 'message' => 'Chat not found or closed']);
}

$ins = $conn->prepare('INSERT INTO control_support_chat_messages (chat_id, sender, message) VALUES (?, \'user\', ?)');
if (!$ins) {
    error_log('support-chat-send prepare failed: ' . $conn->error);
    jsonOut(['success' => false, 'message' => 'Database error']);
}
$ins->bind_param('is', $chatId, $message);
if (!$ins->execute()) {
    error_log('support-chat-send execute failed: ' . $ins->error);
    $ins->close();
    jsonOut(['success' => false, 'message' => 'Failed to send message']);
}
$ins->close();

$conn->query('UPDATE control_support_chats SET updated_at = NOW() WHERE id = ' . (int) $chatId);

jsonOut(['success' => true, 'message' => 'Message sent']);
