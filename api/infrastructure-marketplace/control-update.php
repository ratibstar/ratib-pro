<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/bootstrap.php';

$body = $_POST;
if ($body === []) {
    $raw = file_get_contents('php://input');
    $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$toBool = static function ($v): bool {
    if (is_bool($v)) {
        return $v;
    }
    if (!is_string($v)) {
        return false;
    }
    return in_array(strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true);
};

$toInt = static function ($v, int $default): int {
    if (!is_scalar($v)) {
        return $default;
    }
    $n = (int) $v;
    return $n > 0 ? $n : $default;
};

$allowRaw = isset($body['tenant_allowlist']) ? (string) $body['tenant_allowlist'] : '';
$allow = [];
if (trim($allowRaw) !== '') {
    $parts = explode(',', $allowRaw);
    foreach ($parts as $p) {
        $id = (int) trim($p);
        if ($id > 0) {
            $allow[] = $id;
        }
    }
}

$queueDriverRaw = isset($body['queue_driver']) ? strtolower(trim((string) $body['queue_driver'])) : 'sync';
$queueDriver = in_array($queueDriverRaw, ['sync', 'database', 'redis'], true) ? $queueDriverRaw : 'sync';

$currencyRaw = isset($body['default_currency']) ? strtoupper(trim((string) $body['default_currency'])) : 'USD';
$currency = preg_match('/^[A-Z]{3}$/', $currencyRaw) === 1 ? $currencyRaw : 'USD';

$overrides = [
    'enabled' => $toBool($body['enabled'] ?? '0'),
    'dry_run' => $toBool($body['dry_run'] ?? '0'),
    'execution_kill_switch' => $toBool($body['execution_kill_switch'] ?? '0'),
    'queue_driver' => $queueDriver,
    'queue_max_attempts' => $toInt($body['queue_max_attempts'] ?? 5, 5),
    'queue_pressure_threshold' => max(100, $toInt($body['queue_pressure_threshold'] ?? 2000, 2000)),
    'worker_max_loop_jobs' => $toInt($body['worker_max_loop_jobs'] ?? 1000, 1000),
    'default_currency' => $currency,
    'tenant_allowlist' => $allow,
];

$target = dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/Config/runtime-overrides.json';
$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to create runtime config directory'], JSON_UNESCAPED_SLASHES);
    exit;
}

$written = @file_put_contents($target, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to write runtime overrides file'], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Infrastructure control settings updated',
    'file' => '/modules/infrastructure-marketplace/Config/runtime-overrides.json',
    'overrides' => $overrides,
], JSON_UNESCAPED_SLASHES);
