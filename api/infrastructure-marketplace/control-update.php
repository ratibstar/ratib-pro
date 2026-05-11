<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

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
require_once dirname(__DIR__, 2) . '/control-panel/includes/control-permissions.php';

use Ratib\InfrastructureMarketplace\Audit\RuntimeConfigAuditLogger;
use Ratib\InfrastructureMarketplace\Config\RuntimeOverrideStore;

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * @param int $code
 * @param array<string, mixed> $payload
 */
$respond = static function (int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
};

$username = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
$isLoggedIn = !empty($_SESSION['control_logged_in']);
$isAdmin = $username === 'admin';
$hasSystemAccess = function_exists('hasControlPermission') && (
    hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS)
    || hasControlPermission('edit_control_system_settings')
    || hasControlPermission('view_control_system_settings')
);

if (!$isLoggedIn || (!$isAdmin && !$hasSystemAccess)) {
    $respond(403, ['ok' => false, 'message' => 'Unauthorized runtime config update request']);
}

$sessionCsrf = (string) ($_SESSION['infra_control_csrf_token'] ?? '');
if ($sessionCsrf === '') {
    $respond(419, ['ok' => false, 'message' => 'Missing CSRF session token']);
}

$body = $_POST;
$raw = '';
if ($body === []) {
    $raw = (string) file_get_contents('php://input');
    $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$bodyCsrf = trim((string) ($body['csrf_token'] ?? ''));
$headerCsrf = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
$csrfCandidate = $bodyCsrf !== '' ? $bodyCsrf : $headerCsrf;
if ($csrfCandidate === '' || !hash_equals($sessionCsrf, $csrfCandidate)) {
    $respond(419, ['ok' => false, 'message' => 'Invalid CSRF token']);
}

$allowedInputKeys = [
    'csrf_token',
    'source',
    'enabled',
    'dry_run',
    'execution_kill_switch',
    'queue_driver',
    'queue_max_attempts',
    'queue_pressure_threshold',
    'worker_max_loop_jobs',
    'default_currency',
    'tenant_allowlist',
];

foreach (array_keys($body) as $k) {
    if (!in_array((string) $k, $allowedInputKeys, true)) {
        $respond(422, ['ok' => false, 'message' => 'Unknown config input key: ' . (string) $k]);
    }
}

$sourceRaw = strtolower(trim((string) ($body['source'] ?? '')));
$source = in_array($sourceRaw, ['ui', 'api'], true) ? $sourceRaw : 'api';

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

$overridesNext = [
    'enabled' => array_key_exists('enabled', $body) ? $toBool($body['enabled']) : false,
    'dry_run' => array_key_exists('dry_run', $body) ? $toBool($body['dry_run']) : false,
    'execution_kill_switch' => array_key_exists('execution_kill_switch', $body) ? $toBool($body['execution_kill_switch']) : false,
    'queue_driver' => $queueDriver,
    'queue_max_attempts' => $toInt($body['queue_max_attempts'] ?? 5, 5),
    'queue_pressure_threshold' => max(100, $toInt($body['queue_pressure_threshold'] ?? 2000, 2000)),
    'worker_max_loop_jobs' => $toInt($body['worker_max_loop_jobs'] ?? 1000, 1000),
    'default_currency' => $currency,
    'tenant_allowlist' => $allow,
];

$oldOverrides = [];
$newOverrides = [];
try {
    $updated = RuntimeOverrideStore::updateAtomic(
        /**
         * @param array<string, mixed> $existing
         * @return array<string, mixed>
         */
        static function (array $existing) use ($overridesNext): array {
            // Allow future fields in file to remain intact; only replace controlled keys.
            return array_merge($existing, $overridesNext);
        }
    );
    $oldOverrides = $updated['old'];
    $newOverrides = $updated['new'];
} catch (\Throwable $e) {
    $respond(500, ['ok' => false, 'message' => 'Unable to write runtime overrides file']);
}

/** @var array<string, array{old:mixed,new:mixed}> $changes */
$changes = [];
foreach (array_keys($overridesNext) as $k) {
    $old = $oldOverrides[$k] ?? null;
    $new = $newOverrides[$k] ?? null;
    if (json_encode($old, JSON_UNESCAPED_SLASHES) !== json_encode($new, JSON_UNESCAPED_SLASHES)) {
        $changes[$k] = ['old' => $old, 'new' => $new];
    }
}

$audit = new RuntimeConfigAuditLogger();
$audit->append([
    'event' => 'runtime_config_update',
    'timestamp' => gmdate('c'),
    'actor' => [
        'username' => (string) ($_SESSION['control_username'] ?? 'unknown'),
        'control_admin_id' => isset($_SESSION['control_admin_id']) ? (int) $_SESSION['control_admin_id'] : null,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ],
    'source' => $source,
    'changed_keys' => array_keys($changes),
    'changes' => $changes,
]);

echo json_encode([
    'ok' => true,
    'message' => 'Infrastructure control settings updated',
    'file' => '/modules/infrastructure-marketplace/Config/runtime-overrides.json',
    'overrides' => $newOverrides,
    'changed_keys' => array_keys($changes),
], JSON_UNESCAPED_SLASHES);
