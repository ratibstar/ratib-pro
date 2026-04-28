<?php
/**
 * EN: Handles API endpoint/business logic in `api/ngenius-health.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/ngenius-health.php`.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Endpoint classification for global tenant guard.
define('SYSTEM_ENDPOINT', true);
require_once __DIR__ . '/../includes/config.php';
$isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user()
    && (int) ($_SESSION['role_id'] ?? 0) === 1;
$isControlLogged = !empty($_SESSION['control_logged_in']);
if (!$isAppAdmin && !$isControlLogged) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Access denied'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../includes/payment_api_bootstrap.php';
require_once __DIR__ . '/../includes/ngenius.php';

$apiKey = (string) ratib_ngenius_env('NGENIUS_API_KEY', '');
$apiSecret = (string) ratib_ngenius_env('NGENIUS_API_SECRET', '');
$outletId = (string) ratib_ngenius_env('NGENIUS_OUTLET_ID', '');
$fallbackBase = NGENIUS_DEFAULT_API_BASE_KSA;
$identityBase = rtrim((string) ratib_ngenius_env('NGENIUS_IDENTITY_BASE', (string) ratib_ngenius_env('NGENIUS_API_BASE', $fallbackBase)), '/');
$orderBase = rtrim((string) ratib_ngenius_env('NGENIUS_ORDER_BASE', (string) ratib_ngenius_env('NGENIUS_API_BASE', $fallbackBase)), '/');
$tokenUrl = trim((string) ratib_ngenius_env('NGENIUS_TOKEN_URL', ''));
$realm = trim((string) ratib_ngenius_env('NGENIUS_REALM', 'networkinternational'));
if ($realm === '') {
    $realm = 'networkinternational';
}

if ($apiKey === '' || $outletId === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'message' => 'Missing N-Genius configuration (need NGENIUS_API_KEY and NGENIUS_OUTLET_ID).',
        'payment_config' => [
            'token_url' => $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token'),
            'identity_base' => $identityBase,
            'order_base' => $orderBase,
            'realm' => $realm,
            'api_key_length' => strlen($apiKey),
            'has_api_secret' => $apiSecret !== '',
            'credential_hint' => function_exists('ngenius_api_key_shape') ? ngenius_api_key_shape($apiKey) : null,
            'backend_release' => 'ngenius-health-v1',
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$tokenRes = ngenius_fetch_access_token($identityBase, $apiKey, $apiSecret, $tokenUrl === '' ? null : $tokenUrl, $realm);
$status = (int) ($tokenRes['http_status'] ?? 0);
$ok = (bool) ($tokenRes['ok'] ?? false);

$payload = [
    'ok' => $ok && $status >= 200 && $status < 300,
    'message' => $ok ? 'Token fetch succeeded.' : 'Token fetch failed.',
    'http_status' => $status,
    'curl_error' => (string) ($tokenRes['curl_error'] ?? ''),
    'identity_hint' => ngenius_identity_response_hint((string) ($tokenRes['body'] ?? '')),
    'payment_config' => [
        'token_url' => $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token'),
        'identity_base' => $identityBase,
        'order_base' => $orderBase,
        'realm' => $realm,
        'api_key_length' => strlen($apiKey),
        'has_api_secret' => $apiSecret !== '',
        'credential_hint' => function_exists('ngenius_api_key_shape') ? ngenius_api_key_shape($apiKey) : null,
        'backend_release' => 'ngenius-health-v1',
    ],
];

if (!$ok && $status >= 400 && $status < 500) {
    $payload['identity_error'] = substr((string) ($tokenRes['body'] ?? ''), 0, 800);
}

http_response_code($payload['ok'] ? 200 : 502);
echo json_encode($payload, JSON_UNESCAPED_SLASHES);
