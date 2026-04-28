<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-poll.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-poll.php`.
 */
/**
 * Public API: Poll for new admin messages (control panel storage)
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
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
        $json = '{"success":false,"messages":[]}';
    }
    echo $json;
    exit;
}

$conn = ratib_support_chat_db();
if (!$conn) {
    jsonOut(['success' => true, 'messages' => []]);
}

$chatId = (int)($_GET['chat_id'] ?? 0);
$chatToken = substr(trim((string)($_GET['chat_token'] ?? '')), 0, 64);
$afterId = (int)($_GET['after_id'] ?? 0);

if ($chatId <= 0 || $chatToken === '') {
    jsonOut(['success' => true, 'messages' => []]);
}

$stmt = $conn->prepare('SELECT id, status FROM control_support_chats WHERE id = ? AND chat_token = ?');
if (!$stmt) {
    jsonOut(['success' => false, 'messages' => []]);
}
$stmt->bind_param('is', $chatId, $chatToken);
$stmt->execute();
$resChat = $stmt->get_result();
$chatRow = $resChat ? $resChat->fetch_assoc() : null;
if (!$chatRow) {
    $stmt->close();
    jsonOut(['success' => false, 'messages' => []]);
}
$stmt->close();
if (($chatRow['status'] ?? 'open') !== 'open') {
    jsonOut(['success' => true, 'messages' => [], 'chat_closed' => true]);
}

$cid = (int) $chatId;
$aid = (int) $afterId;
$sql = "SELECT id, sender, message, created_at FROM control_support_chat_messages WHERE chat_id = {$cid} AND sender = 'support'";
if ($aid > 0) {
    $sql .= ' AND id > ' . $aid;
}
$sql .= ' ORDER BY id ASC LIMIT 200';

$res = $conn->query($sql);
$messages = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $messages[] = [
            'id' => (int) $r['id'],
            'sender' => 'support',
            'text' => $r['message'],
            'created_at' => $r['created_at'],
        ];
    }
}

jsonOut(['success' => true, 'messages' => $messages]);
