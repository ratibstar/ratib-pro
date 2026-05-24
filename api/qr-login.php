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

function qr_ctx_from_input(array $input): array
{
    $ctx = [
        'country_id' => (int) ($input['country_id'] ?? 0),
        'agency_id' => (int) ($input['agency_id'] ?? 0),
        'country_slug' => trim((string) ($input['country_slug'] ?? '')),
        'country_name' => trim((string) ($input['country_name'] ?? '')),
        'agency_name' => trim((string) ($input['agency_name'] ?? '')),
        'control' => !empty($input['control']) ? 1 : 0,
        'trust_device' => !empty($input['trust_device']),
        'device_label' => trim((string) ($input['device_label'] ?? 'Mobile')),
        'skip_pin' => !empty($input['skip_pin']),
    ];
    if (function_exists('ratib_qr_login_enrich_context')) {
        $pair = isset($input['pair_token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $input['pair_token'])) : '';
        $ctx = ratib_qr_login_enrich_context($ctx, strlen($pair) === 32 ? $pair : null);
    }
    return $ctx;
}

/**
 * @return array{ok:bool, conn?:mysqli, user_id?:int, username?:string, message?:string}
 */
function qr_trusted_resolve(string $deviceToken, array $input): array
{
    $dev = preg_replace('/[^a-f0-9]/', '', strtolower($deviceToken));
    if (strlen($dev) < 32) {
        return ['ok' => false, 'message' => 'No device token.'];
    }
    $ctx = qr_ctx_from_input($input);
    $conn = ratib_barcode_login_resolve_connection($ctx);
    if (!($conn instanceof mysqli)) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }
    $v = ratib_qr_trusted_device_validate_cookie($conn, $dev);
    if (empty($v['ok'])) {
        return ['ok' => false, 'message' => 'Device not trusted.'];
    }
    $uid = (int) ($v['user_id'] ?? 0);
    $row = ratib_qr_workforce_user_row($conn, $uid);
    if (!$row) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    return [
        'ok' => true,
        'conn' => $conn,
        'user_id' => $uid,
        'username' => (string) ($row['username'] ?? ''),
        'user_row' => $row,
    ];
}

function qr_finish_login(array $auth, ?string $pairToken, bool $applySession): void
{
    if ($pairToken !== '' && strlen($pairToken) === 32) {
        require_once __DIR__ . '/../includes/ratib-barcode-login-pair.php';
        if (!ratib_barcode_pair_approve($pairToken, $auth['session'])) {
            qr_json(['success' => false, 'message' => 'Could not complete desktop login.', 'code' => 'pair_failed'], 500);
        }
    }
    $trustedDev = null;
    if (!empty($auth['session']['_trusted_device_token'])) {
        $trustedDev = (string) $auth['session']['_trusted_device_token'];
        unset($auth['session']['_trusted_device_token']);
    }
    if ($applySession && function_exists('ratib_qr_login_apply_session')) {
        ratib_qr_login_apply_session($auth['session']);
    }
    if ($trustedDev !== null && function_exists('ratib_qr_set_device_cookie')) {
        ratib_qr_set_device_cookie($trustedDev);
    }
    $username = (string) ($auth['session']['username'] ?? '');
    qr_json([
        'success' => true,
        'message' => 'OK',
        'paired' => $pairToken !== '',
        'username' => $username,
        'redirect' => '/pages/dashboard.php',
    ]);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';

try {
    if (in_array($action, ['validate', 'submit', 'validate_pin', 'trusted_check', 'trusted_login', 'metrics'], true)) {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/ratib-qr-login.php';
        require_once __DIR__ . '/../includes/ratib-qr-workforce-identity.php';
    }

    if ($action === 'validate' || $action === 'submit') {
        $payload = trim((string) ($input['qr_payload'] ?? $input['barcode'] ?? ''));
        $pairToken = isset($input['pair_token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $input['pair_token'])) : '';
        if ($pairToken !== '' && strlen($pairToken) !== 32) {
            $pairToken = '';
        }
        $ctx = qr_ctx_from_input($input);
        if ($payload === '') {
            qr_json(['success' => false, 'message' => 'No QR data.', 'code' => 'invalid'], 400);
        }
        $auth = ratib_qr_login_authenticate_payload($payload, $ctx, $pairToken !== '' ? $pairToken : null);
        if (!empty($auth['needs_pin'])) {
            qr_json([
                'success' => false,
                'needs_pin' => true,
                'challenge_token' => $auth['challenge_token'] ?? '',
                'message' => $auth['message'] ?? 'Enter PIN.',
                'code' => 'needs_pin',
            ], 200);
        }
        if (empty($auth['ok'])) {
            $code = 401;
            $c = (string) ($auth['code'] ?? 'invalid');
            if ($c === 'rate_limit') {
                $code = 429;
            } elseif ($c === 'expired') {
                $code = 410;
            } elseif ($c === 'revoked' || $c === 'disabled') {
                $code = 403;
            }
            qr_json([
                'success' => false,
                'message' => $auth['message'] ?? 'Validation failed.',
                'code' => $c,
            ], $code);
        }
        $applySession = ($pairToken === '');
        qr_finish_login($auth, $pairToken, $applySession);
    }

    if ($action === 'validate_pin') {
        $challenge = trim((string) ($input['challenge_token'] ?? ''));
        $pin = (string) ($input['pin'] ?? '');
        $pairToken = isset($input['pair_token']) ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $input['pair_token'])) : '';
        if ($pairToken !== '' && strlen($pairToken) !== 32) {
            $pairToken = '';
        }
        $ctx = qr_ctx_from_input($input);
        if ($challenge === '') {
            qr_json(['success' => false, 'message' => 'Missing challenge.', 'code' => 'invalid'], 400);
        }
        $auth = ratib_qr_login_complete_with_pin($challenge, $pin, $ctx);
        if (empty($auth['ok'])) {
            qr_json([
                'success' => false,
                'message' => $auth['message'] ?? 'PIN failed.',
                'code' => $auth['code'] ?? 'pin_invalid',
            ], 401);
        }
        $pairFromChallenge = (string) ($auth['pair_token'] ?? $pairToken);
        $applySession = ($pairFromChallenge === '');
        qr_finish_login($auth, $pairFromChallenge, $applySession);
    }

    if ($action === 'trusted_check') {
        $dev = (string) ($_COOKIE['ratib_device'] ?? '');
        if ($dev === '') {
            qr_json(['success' => true, 'trusted' => false]);
        }
        $resolved = qr_trusted_resolve($dev, $input);
        if (empty($resolved['ok'])) {
            qr_json(['success' => true, 'trusted' => false]);
        }
        qr_json([
            'success' => true,
            'trusted' => true,
            'user_id' => (int) ($resolved['user_id'] ?? 0),
            'username' => (string) ($resolved['username'] ?? ''),
        ]);
    }

    if ($action === 'trusted_login') {
        $dev = (string) ($_COOKIE['ratib_device'] ?? $input['device_token'] ?? '');
        $resolved = qr_trusted_resolve($dev, $input);
        if (empty($resolved['ok'])) {
            qr_json(['success' => false, 'message' => $resolved['message'] ?? 'Device not trusted.', 'code' => 'untrusted'], 403);
        }
        $conn = $resolved['conn'];
        $row = $resolved['user_row'];
        if (!isset($row['user_id'])) {
            $pk = ratib_users_primary_key_for_barcode($conn);
            $row['user_id'] = (int) ($row[$pk] ?? $resolved['user_id']);
        }
        $ctx = qr_ctx_from_input($input);
        $session = ratib_barcode_login_build_session($conn, $row, $ctx);
        if ($session === null) {
            qr_json(['success' => false, 'message' => 'Account inactive.'], 403);
        }
        ratib_qr_login_apply_session($session);
        ratib_qr_login_audit($conn, 'trusted_login', 'ok', (int) $resolved['user_id']);
        $redirect = '/pages/dashboard.php';
        if (function_exists('ratib_country_dashboard_url')) {
            $redirect = ratib_country_dashboard_url((int) ($_SESSION['agency_id'] ?? $ctx['agency_id'] ?? 0));
        }
        qr_json(['success' => true, 'redirect' => $redirect]);
    }

    if ($action === 'metrics') {
        if (empty($_SESSION['logged_in'])) {
            qr_json(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            qr_json(['success' => false, 'message' => 'Database unavailable.'], 500);
        }
        qr_json(['success' => true, 'metrics' => ratib_qr_workforce_metrics_snapshot($conn)]);
    }

    if (in_array($action, ['issue', 'revoke', 'ensure', 'regenerate', 'status'], true)) {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../includes/ratib-qr-login.php';
        require_once __DIR__ . '/../includes/ratib-qr-workforce-identity.php';
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
        if ($action === 'status') {
            qr_json(['success' => true, 'data' => ratib_qr_workforce_status($conn, $userId)]);
        }
        if ($action === 'ensure') {
            $issued = ratib_qr_login_ensure_persistent_token($conn, $userId, false);
            if (empty($issued['ok'])) {
                qr_json(['success' => false, 'message' => $issued['message'] ?? 'Failed.'], 500);
            }
            $badgeUrl = '';
            if (!empty($issued['qr_payload']) && function_exists('ratib_qr_login_badge_url')) {
                $badgeUrl = ratib_qr_login_badge_url((string) $issued['qr_payload'], ratib_qr_login_badge_tenant_context());
            }
            qr_json([
                'success' => true,
                'qr_payload' => $issued['qr_payload'] ?? null,
                'badge_url' => $badgeUrl,
                'expires_at' => $issued['expires_at'] ?? null,
                'status' => $issued['status'] ?? 'active',
                'regenerated' => !empty($issued['regenerated']),
            ]);
        }
        if ($action === 'regenerate' || $action === 'issue') {
            $ttl = (int) ($input['ttl_seconds'] ?? 0);
            $issued = ratib_qr_login_issue_token($conn, $userId, $ttl, true);
            if (empty($issued['ok'])) {
                qr_json(['success' => false, 'message' => $issued['message'] ?? 'Issue failed.'], 500);
            }
            qr_json([
                'success' => true,
                'qr_payload' => $issued['qr_payload'],
                'expires_at' => $issued['expires_at'],
                'badge_url' => ratib_qr_login_badge_url((string) $issued['qr_payload'], ratib_qr_login_badge_tenant_context()),
            ]);
        }
    }

    qr_json(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Throwable $e) {
    error_log('qr-login: ' . $e->getMessage());
    qr_json(['success' => false, 'message' => 'Server error.'], 500);
}
