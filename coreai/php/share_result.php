<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = coreai_require_auth(true);
$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$execution = is_array($payload['execution'] ?? null) ? $payload['execution'] : [];
$aiExplanation = trim((string)($payload['ai_explanation'] ?? ''));
$shareCard = is_array($payload['share_card'] ?? null) ? $payload['share_card'] : [];
if ($execution === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Execution payload is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($aiExplanation === '') {
    $aiExplanation = 'CoreAI executed approved steps and produced this result.';
}

$diffItems = [];
foreach (($execution['groups'] ?? []) as $group) {
    if (!is_array($group)) {
        continue;
    }
    foreach (($group['results'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $before = (string)($row['before_content'] ?? '');
        $after = (string)($row['after_content'] ?? '');
        $target = (string)($row['target'] ?? '');
        $diffItems[] = [
            'target' => $target,
            'operation' => (string)($row['operation'] ?? ''),
            'diff' => coreai_build_text_diff($before, $after),
        ];
    }
}

$shareToken = coreai_save_public_share([
    'owner_user_id' => (string)($user['id'] ?? ''),
    'owner_username' => (string)($user['username'] ?? ''),
    'execution_summary' => (string)($execution['summary'] ?? 'Execution result'),
    'ai_explanation' => $aiExplanation,
    'share_card' => [
        'diff_summary' => (string)($shareCard['diff_summary'] ?? ''),
        'lines_changed' => (int)($shareCard['lines_changed'] ?? 0),
        'performance_gain' => (int)($shareCard['performance_gain'] ?? 0),
        'stability_gain' => (int)($shareCard['stability_gain'] ?? 0),
    ],
    'diff_items' => $diffItems,
]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/coreai/php/share_result.php');
$coreaiBasePath = preg_replace('#/php/share_result\.php.*$#', '', $requestUri) ?: '/coreai';
$baseUrl = $scheme . '://' . $host;
$shareUrl = rtrim($baseUrl, '/') . rtrim($coreaiBasePath, '/') . '/share.php?token=' . rawurlencode($shareToken);

echo json_encode([
    'ok' => true,
    'share_token' => $shareToken,
    'share_url' => $shareUrl,
], JSON_UNESCAPED_UNICODE);
