<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Execution planning endpoint (pre-approval stage).
 * نقطة توليد خطة التنفيذ (قبل الموافقة).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = coreai_require_auth(true);
coreai_enforce_subscription_or_exit($currentUser, 'plan_request', 'planning', false);

$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$groups = $payload['actionGroups'] ?? [];
if (!is_array($groups) || $groups === []) {
    http_response_code(400);
    echo json_encode(['error' => 'No action groups provided.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$validation = coreai_validate_action_groups($groups);
if (($validation['ok'] ?? false) !== true) {
    http_response_code(400);
    echo json_encode(['error' => $validation['error'] ?? 'Unsafe action groups.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Build and persist execution_plan.json before approval.
 * إنشاء وحفظ execution_plan.json قبل الموافقة.
 */
$plan = coreai_build_execution_plan($groups);

echo json_encode(
    [
        'ok' => true,
        'plan' => $plan,
        'plan_path' => 'execution_plan.json',
    ],
    JSON_UNESCAPED_UNICODE
);

coreai_track_usage('plan_request', [
    'groups' => count($groups),
]);
