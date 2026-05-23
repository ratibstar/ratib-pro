<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function pair_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/ratib-barcode-login-pair.php';

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';

    if ($action === 'create') {
        $context = [
            'country_id' => (int) ($input['country_id'] ?? 0),
            'agency_id' => (int) ($input['agency_id'] ?? 0),
            'country_slug' => trim((string) ($input['country_slug'] ?? '')),
            'country_name' => trim((string) ($input['country_name'] ?? '')),
            'agency_name' => trim((string) ($input['agency_name'] ?? '')),
            'control' => !empty($input['control']) ? 1 : 0,
        ];
        $created = ratib_barcode_pair_create($context);
        if (empty($created['ok'])) {
            pair_json(['success' => false, 'message' => $created['message'] ?? 'Failed'], 500);
        }
        pair_json(['success' => true, 'token' => $created['token']]);
    }

    if ($action === 'poll') {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($input['token'] ?? '')));
        if (strlen($token) !== 32) {
            pair_json(['success' => false, 'message' => 'Invalid token'], 400);
        }
        $poll = ratib_barcode_pair_poll($token);
        if (empty($poll['ok'])) {
            pair_json(['success' => true, 'status' => 'expired']);
        }
        pair_json(['success' => true, 'status' => $poll['status'] ?? 'pending']);
    }

    if ($action === 'submit') {
        require_once __DIR__ . '/../includes/ratib-barcode-login-auth.php';

        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($input['token'] ?? '')));
        $barcode = trim((string) ($input['barcode'] ?? ''));
        if (strlen($token) !== 32) {
            pair_json(['success' => false, 'message' => 'Invalid session'], 400);
        }
        if ($barcode === '') {
            pair_json(['success' => false, 'message' => 'No barcode scanned'], 400);
        }
        $pair = ratib_barcode_pair_read($token);
        if ($pair === null) {
            pair_json(['success' => false, 'message' => 'Session expired. Scan the QR on your computer again.'], 410);
        }
        if (($pair['status'] ?? '') !== 'pending') {
            pair_json(['success' => false, 'message' => 'This session is no longer waiting.'], 409);
        }
        $ctx = is_array($pair['context'] ?? null) ? $pair['context'] : [];
        $auth = ratib_barcode_login_authenticate($barcode, $ctx);
        if (empty($auth['ok']) || !is_array($auth['session'] ?? null)) {
            pair_json(['success' => false, 'message' => $auth['message'] ?? 'Login failed'], 401);
        }
        if (!ratib_barcode_pair_approve($token, $auth['session'])) {
            pair_json(['success' => false, 'message' => 'Could not complete login'], 500);
        }
        pair_json(['success' => true, 'message' => 'OK — return to your computer.']);
    }

    pair_json(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Throwable $e) {
    error_log('login-barcode-pair: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    pair_json(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
