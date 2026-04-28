<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-agency-unread.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-agency-unread.php`.
 */
/**
 * Ratib Pro: unread live-support chat count for the logged-in agency (by session country).
 * Used by main app navbar badge (not the control panel session).
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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['logged_in'])) {
    jsonOut(['success' => false, 'unread' => 0, 'message' => 'Not logged in']);
}

$countryId = !empty($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : 0;
if ($countryId < 1) {
    jsonOut(['success' => true, 'unread' => 0]);
}

$conn = ratib_support_chat_db();
if (!$conn instanceof mysqli || $conn->connect_errno) {
    jsonOut(['success' => true, 'unread' => 0]);
}

$t1 = @$conn->query("SHOW TABLES LIKE 'control_support_chats'");
$t2 = @$conn->query("SHOW TABLES LIKE 'control_support_chat_messages'");
if (!$t1 || $t1->num_rows === 0 || !$t2 || $t2->num_rows === 0) {
    jsonOut(['success' => true, 'unread' => 0]);
}

$hasCountry = $conn->query("SHOW COLUMNS FROM control_support_chats LIKE 'country_id'")->num_rows > 0;
if (!$hasCountry) {
    jsonOut(['success' => true, 'unread' => 0]);
}

$agencyId = !empty($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : 0;
$hasAgency = $conn->query("SHOW COLUMNS FROM control_support_chats LIKE 'agency_id'")->num_rows > 0;

$where = "c.status = 'open' AND c.country_id = " . (int) $countryId;
if ($hasAgency && $agencyId > 0) {
    $where .= ' AND (c.agency_id IS NULL OR c.agency_id = 0 OR c.agency_id = ' . (int) $agencyId . ')';
}

$sql = "SELECT COUNT(DISTINCT c.id) AS n FROM control_support_chats c WHERE " . $where .
    " AND (c.admin_read_at IS NULL OR EXISTS (
        SELECT 1 FROM control_support_chat_messages m
        WHERE m.chat_id = c.id AND m.sender = 'user'
        AND m.created_at > c.admin_read_at
    ))";

$n = 0;
$res = @$conn->query($sql);
if ($res && ($row = $res->fetch_assoc())) {
    $n = (int) ($row['n'] ?? 0);
}

jsonOut(['success' => true, 'unread' => $n]);
