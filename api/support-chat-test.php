<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-test.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-test.php`.
 */
/**
 * TEMP: create a sample escalated support chat (control panel DB)
 * Use to verify routing & UI without relying on the chat widget phrase.
 * Remove after verification.
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/support_chat_db.php';
mysqli_report(MYSQLI_REPORT_OFF);

$isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()
    && (int) ($_SESSION['role_id'] ?? 0) === 1;
$isControlLogged = !empty($_SESSION['control_logged_in']);
if (!$isAppAdmin && !$isControlLogged) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = ratib_support_chat_db();
if (!$conn || !$conn instanceof mysqli) {
    jsonOut(['success' => false, 'message' => 'Support chat DB not configured']);
}

$countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$agencyId = isset($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
$countryName = isset($_GET['country_name']) ? substr((string)$_GET['country_name'], 0, 255) : '';
$agencyName = isset($_GET['agency_name']) ? substr((string)$_GET['agency_name'], 0, 255) : '';
$sourcePage = isset($_GET['source_page']) ? substr((string)$_GET['source_page'], 0, 255) : 'test';

$chatToken = bin2hex(random_bytes(32));
$now = date('Y-m-d H:i:s');

try {
    $conn->begin_transaction();

    $chatId = null;
    if (ratib_support_chat_has_context_columns($conn)) {
        if ($countryName === '' && $countryId > 0) $countryName = 'Country #' . $countryId;
        if ($agencyName === '' && $agencyId > 0) $agencyName = 'Agency #' . $agencyId;

        $stmt = $conn->prepare(
            'INSERT INTO control_support_chats (chat_token, source_page, visitor_email, visitor_name, country_id, agency_id, country_name, agency_name, status)
             VALUES (?, ?, NULL, NULL, NULLIF(?,0), NULLIF(?,0), ?, ?, \'open\')'
        );
        if (!$stmt) throw new Exception('Prepare chat failed: ' . $conn->error);

        $stmt->bind_param(
            'ssiiss',
            $chatToken,
            $sourcePage,
            $countryId,
            $agencyId,
            $countryName,
            $agencyName
        );
        if (!$stmt->execute()) throw new Exception('Insert chat failed: ' . $stmt->error);
        $chatId = (int)$conn->insert_id;
        $stmt->close();
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO control_support_chats (chat_token, source_page, visitor_email, visitor_name, status)
             VALUES (?, ?, NULL, NULL, \'open\')'
        );
        if (!$stmt) throw new Exception('Prepare chat failed: ' . $conn->error);
        $stmt->bind_param('ss', $chatToken, $sourcePage);
        if (!$stmt->execute()) throw new Exception('Insert chat failed: ' . $stmt->error);
        $chatId = (int)$conn->insert_id;
        $stmt->close();
    }

    if (!$chatId) throw new Exception('Invalid chat id');

    $msgStmt = $conn->prepare(
        'INSERT INTO control_support_chat_messages (chat_id, sender, message)
         VALUES (?, \'user\', ?)'
    );
    if (!$msgStmt) throw new Exception('Prepare message failed: ' . $conn->error);

    $testText = 'I need to talk to support (TEST).';
    $msgStmt->bind_param('is', $chatId, $testText);
    if (!$msgStmt->execute()) throw new Exception('Insert message failed: ' . $msgStmt->error);
    $msgStmt->close();

    $conn->commit();

    jsonOut([
        'success' => true,
        'chat_id' => $chatId,
        'chat_token' => $chatToken,
        'country_id' => $countryId,
        'agency_id' => $agencyId,
        'source_page' => $sourcePage,
    ]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $e2) {}
    error_log('support-chat-test: ' . $e->getMessage());
    jsonOut(['success' => false, 'message' => 'Test chat failed', 'error' => $e->getMessage()]);
}

