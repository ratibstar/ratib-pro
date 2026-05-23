<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!defined('SYSTEM_ENDPOINT')) {
    define('SYSTEM_ENDPOINT', true);
}

function qr_json(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';

try {
    if ($action === 'validate' || $action === 'submit') {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/ratib-qr-login.php';

        $payload = trim((string) ($input['qr_payload'] ?? $input['barcode'] ?? ''));
        $pairToken = isset($input['pair_token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $input['pair_token'])) : '';
        if ($pairToken !== '' && strlen($pairToken) !== 32) {
            $pairToken = '';
        }
        $ctx = [
            'country_id' => (int) ($input['country_id'] ?? 0),
            'agency_id' => (int) ($input['agency_id'] ?? 0),
            'country_slug' => trim((string) ($input['country_slug'] ?? '')),
            'country_name' => trim((string) ($input['country_name'] ?? '')),
            'agency_name' => trim((string) ($input['agency_name'] ?? '')),
            'control' => !empty($input['control']) ? 1 : 0,
        ];
        if ($payload === '') {
            qr_json(['success' => false, 'message' => 'No QR data.', 'code' => 'invalid'], 400);
        }
        $auth = ratib_qr_login_authenticate_payload($payload, $ctx, $pairToken !== '' ? $pairToken : null);
        if (empty($auth['ok'])) {
            $code = 401;
            if (($auth['code'] ?? '') === 'rate_limit') {
                $code = 429;
            } elseif (($auth['code'] ?? '') === 'expired') {
                $code = 410;
            } elseif (($auth['code'] ?? '') === 'revoked') {
                $code = 403;
            }
            qr_json([
                'success' => false,
                'message' => $auth['message'] ?? 'Validation failed.',
                'code' => $auth['code'] ?? 'invalid',
            ], $code);
        }
        if ($pairToken !== '' && strlen($pairToken) === 32) {
            require_once __DIR__ . '/../includes/ratib-barcode-login-pair.php';
            if (!ratib_barcode_pair_approve($pairToken, $auth['session'])) {
                qr_json(['success' => false, 'message' => 'Could not complete desktop login.', 'code' => 'pair_failed'], 500);
            }
        }
        qr_json(['success' => true, 'message' => 'OK', 'paired' => $pairToken !== '']);
    }

    if ($action === 'issue' || $action === 'revoke') {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/ratib-qr-login.php';
        if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
            qr_json(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        $userId = (int) ($input['user_id'] ?? 0);
        if ($userId <= 0) {
            qr_json(['success' => false, 'message' => 'Invalid user.'], 400);
        }
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            qr_json(['success' => false, 'message' => 'Database unavailable.'], 500);
        }
        if ($action === 'revoke') {
            $ok = ratib_qr_login_revoke_token($conn, $userId);
            qr_json(['success' => $ok, 'message' => $ok ? 'Revoked.' : 'Failed.'], $ok ? 200 : 500);
        }
        $ttl = (int) ($input['ttl_seconds'] ?? 31536000);
        $issued = ratib_qr_login_issue_token($conn, $userId, $ttl);
        if (empty($issued['ok'])) {
            qr_json(['success' => false, 'message' => $issued['message'] ?? 'Issue failed.'], 500);
        }
        qr_json([
            'success' => true,
            'qr_payload' => $issued['qr_payload'],
            'expires_at' => $issued['expires_at'],
        ]);
    }

    qr_json(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Throwable $e) {
    error_log('qr-login: ' . $e->getMessage());
    qr_json(['success' => false, 'message' => 'Server error.'], 500);
}
