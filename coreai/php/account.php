<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower(trim((string)($_GET['action'] ?? 'me')));

$rawInput = file_get_contents('php://input');
$payload = [];
if (is_string($rawInput) && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if (isset($_POST['api_key']) && !isset($payload['api_key'])) {
    $payload['api_key'] = (string)$_POST['api_key'];
}
if (isset($_POST['username']) && !isset($payload['username'])) {
    $payload['username'] = (string)$_POST['username'];
}
if (isset($_POST['password']) && !isset($payload['password'])) {
    $payload['password'] = (string)$_POST['password'];
}

if ($method === 'POST' && $action === 'register') {
    $username = trim((string)($payload['username'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $result = coreai_register_user($username, $password);
    if (($result['ok'] ?? false) !== true) {
        http_response_code(400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    coreai_start_session();
    $_SESSION['coreai_user_id'] = (string)($result['user']['id'] ?? '');
    echo json_encode(['ok' => true, 'user' => $result['user']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'login') {
    $username = trim((string)($payload['username'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $result = coreai_authenticate_user($username, $password);
    if (($result['ok'] ?? false) !== true) {
        http_response_code(401);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    coreai_start_session();
    $_SESSION['coreai_user_id'] = (string)($result['user']['id'] ?? '');
    echo json_encode(['ok' => true, 'user' => $result['user']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'logout') {
    coreai_start_session();
    unset($_SESSION['coreai_user_id']);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = coreai_require_auth(true);
$subscriptionState = coreai_get_subscription_state($user);

if ($method === 'POST' && $action === 'api-key') {
    $apiKey = trim((string)($payload['api_key'] ?? ''));
    if ($apiKey === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'api_key is required.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ok = coreai_set_current_user_api_key($apiKey);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to save API key (session/storage).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'set-plan') {
    $plan = strtolower(trim((string)($payload['plan'] ?? '')));
    $ok = coreai_set_current_user_plan($plan);
    if (!$ok) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid or unavailable plan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $user = coreai_require_auth(true);
    $subscriptionState = coreai_get_subscription_state($user);
    echo json_encode([
        'ok' => true,
        'plan' => $subscriptionState['plan_slug'] ?? 'free',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET' && $action === 'usage') {
    $usagePath = coreai_user_context_root() . '/usage.json';
    $usage = [];
    if (is_file($usagePath)) {
        $raw = file_get_contents($usagePath);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $usage = $decoded;
        }
    }
    echo json_encode([
        'ok' => true,
        'usage' => $usage,
        'subscription' => [
            'plan_slug' => $subscriptionState['plan_slug'] ?? 'free',
            'plan' => $subscriptionState['plan'] ?? [],
            'daily' => $subscriptionState['usage_daily'] ?? [],
            'monthly' => $subscriptionState['usage_monthly'] ?? [],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET' && $action === 'team') {
    coreai_enforce_subscription_or_exit($user, 'team_request', 'team_features', false);
    echo json_encode([
        'ok' => true,
        'team' => [
            'team_id' => (string)($user['team_id'] ?? ''),
            'message' => 'Enterprise team features are enabled.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => (string)$user['id'],
        'username' => (string)$user['username'],
        'plan' => (string)($subscriptionState['plan_slug'] ?? 'free'),
        'plan_label' => (string)(($subscriptionState['plan']['label'] ?? 'Free')),
        'features' => $subscriptionState['plan']['features'] ?? [],
        'quotas' => $subscriptionState['plan']['quotas'] ?? [],
        'has_api_key' => trim((string)($user['api_key'] ?? '')) !== '',
        'project_root' => coreai_user_project_root(),
        'context_root' => coreai_user_context_root(),
    ],
], JSON_UNESCAPED_UNICODE);
