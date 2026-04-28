<?php
/**
 * EN: Handles API endpoint/business logic in `api/support-chat-escalate.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/support-chat-escalate.php`.
 */
/**
 * Public API: Escalate chat when user requests live support (e.g. "I need to talk to support").
 * Stores chat in control panel DB so Support Chats sees it for all countries/agencies.
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

/** Normalize message text for suffix matching (avoid duplicate inserts). */
function support_escalate_norm_text($t) {
    $t = str_replace(["\r\n", "\r"], "\n", (string) $t);
    return trim(preg_replace('/[ \t]+/u', ' ', $t));
}

/** @return list<array{sender:string,text:string}> */
function support_escalate_normalize_conversation(array $conversation) {
    $rows = [];
    foreach ($conversation as $msg) {
        $sender = (strtolower((string) ($msg['sender'] ?? '')) === 'user') ? 'user' : 'support';
        $text = substr(support_escalate_norm_text((string) ($msg['text'] ?? '')), 0, 8000);
        if ($text === '') {
            continue;
        }
        $rows[] = ['sender' => $sender, 'text' => $text];
    }
    return $rows;
}

/** Longest matching suffix length between two normalized row lists. */
function support_escalate_suffix_match_len(array $dbRows, array $convRows) {
    $max = min(count($dbRows), count($convRows));
    for ($len = $max; $len >= 0; $len--) {
        if ($len === 0) {
            return 0;
        }
        $ok = true;
        for ($i = 0; $i < $len; $i++) {
            $di = count($dbRows) - $len + $i;
            $ci = count($convRows) - $len + $i;
            if ($dbRows[$di]['sender'] !== $convRows[$ci]['sender'] || $dbRows[$di]['text'] !== $convRows[$ci]['text']) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $len;
        }
    }
    return 0;
}

/**
 * Rows to append: compare tail of conversation to DB tail (suffix), insert only new prefix of conv tail.
 *
 * @param list<array{sender:string,text:string}> $dbRows chronological
 * @param list<array{sender:string,text:string}> $convRows chronological
 * @return list<array{sender:string,text:string}>
 */
function support_escalate_diff_new_messages(array $dbRows, array $convRows) {
    if (empty($convRows)) {
        return [];
    }
    $tailLen = min(100, count($convRows));
    $convTail = array_slice($convRows, -$tailLen);
    $matched = support_escalate_suffix_match_len($dbRows, $convTail);
    return array_slice($convTail, 0, count($convTail) - $matched);
}

$conn = ratib_support_chat_db();
if (!$conn) {
    jsonOut(['success' => false, 'message' => 'Support chat is not configured']);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$conversation = is_array($input['conversation'] ?? null) ? $input['conversation'] : [];
$sourcePage = substr(trim((string)($input['source_page'] ?? '')), 0, 255);
$visitorEmail = substr(trim((string)($input['visitor_email'] ?? '')), 0, 255);
$visitorName = substr(trim((string)($input['visitor_name'] ?? '')), 0, 255);
$resumeTokenIn = preg_replace('/[^a-f0-9]/i', '', (string) ($input['resume_chat_token'] ?? ''));

if (empty($conversation)) {
    jsonOut(['success' => false, 'message' => 'Conversation history is required']);
}
$conversation = array_slice($conversation, -100);

$countryId = !empty($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : 0;
$agencyId = !empty($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : 0;
$countryName = substr(trim((string)($_SESSION['country_name'] ?? '')), 0, 255);
$agencyName = substr(trim((string)($_SESSION['agency_name'] ?? '')), 0, 255);
if ($countryName === '' && $countryId > 0) {
    $countryName = 'Country #' . $countryId;
}
if ($agencyName === '' && $agencyId > 0) {
    $agencyName = 'Agency #' . $agencyId;
}

$convRows = support_escalate_normalize_conversation($conversation);
if (empty($convRows)) {
    jsonOut(['success' => false, 'message' => 'Conversation history is required']);
}

$conn->begin_transaction();
try {
    $lastMsgText = substr($convRows[count($convRows) - 1]['text'] ?? '', 0, 120);
    error_log('support-chat-escalate host=' . ($_SERVER['HTTP_HOST'] ?? '') . ' source_page=' . $sourcePage . ' last_msg=' . $lastMsgText . ' country_id=' . $countryId . ' agency_id=' . $agencyId);

    $hasCtx = ratib_support_chat_has_context_columns($conn);
    $reuseId = 0;
    $reuseToken = '';

    if ($hasCtx && strlen($resumeTokenIn) >= 32) {
        $st = $conn->prepare('SELECT id, chat_token, IFNULL(country_id, 0) AS cid, IFNULL(agency_id, 0) AS aid FROM control_support_chats WHERE chat_token = ? AND status = \'open\' LIMIT 1');
        if ($st) {
            $st->bind_param('s', $resumeTokenIn);
            $st->execute();
            $rs = $st->get_result();
            $row = $rs ? $rs->fetch_assoc() : null;
            $st->close();
            if ($row && (int) $row['cid'] === $countryId && (int) $row['aid'] === $agencyId) {
                $reuseId = (int) $row['id'];
                $reuseToken = (string) $row['chat_token'];
            }
        }
    }

    if ($reuseId <= 0 && $hasCtx && $countryId > 0) {
        $st = $conn->prepare('SELECT id, chat_token FROM control_support_chats WHERE status = \'open\' AND IFNULL(country_id, 0) = ? AND IFNULL(agency_id, 0) = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        if ($st) {
            $st->bind_param('ii', $countryId, $agencyId);
            $st->execute();
            $rs = $st->get_result();
            $row = $rs ? $rs->fetch_assoc() : null;
            $st->close();
            if ($row) {
                $reuseId = (int) $row['id'];
                $reuseToken = (string) $row['chat_token'];
            }
        }
    }

    if ($reuseId > 0 && $reuseToken !== '') {
        $chatId = $reuseId;
        $chatToken = $reuseToken;

        $tailLimit = min(2000, max(200, count($convRows) * 3));
        $dbRows = [];
        $q = $conn->query('SELECT sender, message FROM control_support_chat_messages WHERE chat_id = ' . (int) $chatId . ' ORDER BY id DESC LIMIT ' . (int) $tailLimit);
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $dbRows[] = [
                    'sender' => (string) $r['sender'],
                    'text' => support_escalate_norm_text((string) $r['message']),
                ];
            }
        }
        $dbRows = array_reverse($dbRows);

        $toInsert = support_escalate_diff_new_messages($dbRows, $convRows);

        $msgStmt = $conn->prepare('INSERT INTO control_support_chat_messages (chat_id, sender, message) VALUES (?, ?, ?)');
        if (!$msgStmt) {
            throw new Exception('Prepare messages failed: ' . $conn->error);
        }
        foreach ($toInsert as $row) {
            $s = $row['sender'];
            $t = $row['text'];
            $msgStmt->bind_param('iss', $chatId, $s, $t);
            if (!$msgStmt->execute()) {
                $msgStmt->close();
                throw new Exception('Insert message failed');
            }
        }
        $msgStmt->close();

        $srcEsc = $conn->real_escape_string($sourcePage);
        $emEsc = $conn->real_escape_string($visitorEmail);
        $nmEsc = $conn->real_escape_string($visitorName);
        $cnEsc = $conn->real_escape_string($countryName);
        $anEsc = $conn->real_escape_string($agencyName);
        $conn->query('UPDATE control_support_chats SET updated_at = NOW(), source_page = \'' . $srcEsc . '\', visitor_email = NULLIF(\'' . $emEsc . '\',\'\'), visitor_name = NULLIF(\'' . $nmEsc . '\',\'\'), country_name = NULLIF(\'' . $cnEsc . '\',\'\'), agency_name = NULLIF(\'' . $anEsc . '\',\'\') WHERE id = ' . (int) $chatId);

        $conn->commit();
        jsonOut([
            'success' => true,
            'chat_id' => $chatId,
            'chat_token' => $chatToken,
            'reused' => true,
            'message' => 'Thank you — our team will contact you soon. Stay on this chat for replies.',
        ]);
    }

    $chatToken = bin2hex(random_bytes(32));

    if ($hasCtx) {
        $stmt = $conn->prepare('INSERT INTO control_support_chats (chat_token, source_page, visitor_email, visitor_name, country_id, agency_id, country_name, agency_name, status) VALUES (?, ?, ?, ?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,\'\'), NULLIF(?,\'\'), \'open\')');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param(
            'ssssiiss',
            $chatToken,
            $sourcePage,
            $visitorEmail,
            $visitorName,
            $countryId,
            $agencyId,
            $countryName,
            $agencyName
        );
    } else {
        $stmt = $conn->prepare("INSERT INTO control_support_chats (chat_token, source_page, visitor_email, visitor_name, status) VALUES (?, ?, ?, ?, 'open')");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('ssss', $chatToken, $sourcePage, $visitorEmail, $visitorName);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Insert chat failed');
    }
    $chatId = (int) $conn->insert_id;
    $stmt->close();
    if ($chatId <= 0) {
        throw new Exception('Invalid chat id');
    }

    $msgStmt = $conn->prepare('INSERT INTO control_support_chat_messages (chat_id, sender, message) VALUES (?, ?, ?)');
    if (!$msgStmt) {
        throw new Exception('Prepare messages failed: ' . $conn->error);
    }
    foreach ($convRows as $row) {
        $s = $row['sender'];
        $t = $row['text'];
        $msgStmt->bind_param('iss', $chatId, $s, $t);
        if (!$msgStmt->execute()) {
            $msgStmt->close();
            throw new Exception('Insert message failed');
        }
    }
    $msgStmt->close();

    $conn->commit();
    jsonOut([
        'success' => true,
        'chat_id' => $chatId,
        'chat_token' => $chatToken,
        'reused' => false,
        'message' => 'Thank you — our team will contact you soon. Stay on this chat for replies.',
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $e2) {
        error_log('Support chat rollback error: ' . $e2->getMessage());
    }
    error_log('Support chat escalate error: ' . $e->getMessage());
    jsonOut(['success' => false, 'message' => 'Could not create support chat']);
}
