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

use Ratib\InfrastructureMarketplace\Audit\RuntimeConfigAuditLogger;
use Ratib\InfrastructureMarketplace\Config\RuntimeOverrideStore;
use Ratib\InfrastructureMarketplace\Diagnostics\ProviderDiagnosticsService;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;
use Ratib\InfrastructureMarketplace\Security\Secrets\InfraProviderSecretsSync;

/**
 * @param int $code
 * @param array<string, mixed> $payload
 */
$respond = static function (int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
};

$body = $_POST;
if ($body === []) {
    $raw = (string) file_get_contents('php://input');
    $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

ControlSecurityGuard::enforce('control-update', ControlSecurityGuard::TIER_CONTROL_SYSTEM, [
    'json_body' => $body,
    'require_csrf' => true,
]);

$knownKeys = [
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
    'cpanel_base_url',
    'cpanel_username',
    'cpanel_api_token',
    'tenant_allowlist',
    'runtime_controls_submit',
    'nc_api_user',
    'nc_api_key',
    'nc_username',
    'nc_client_ip',
    'pf_namecheap_live',
    'pf_namecheap_sandbox',
    'pf_cloudflare_dns_live',
    'pf_cloudflare_dns_sandbox',
    'pf_letsencrypt_ssl_live',
    'pf_letsencrypt_ssl_sandbox',
];

foreach (array_keys($body) as $k) {
    $ks = (string) $k;
    if (in_array($ks, $knownKeys, true)) {
        continue;
    }
    $respond(422, ['ok' => false, 'message' => 'Unknown config input key: ' . $ks]);
}

$sourceRaw = strtolower(trim((string) ($body['source'] ?? '')));
$source = in_array($sourceRaw, ['ui', 'api'], true) ? $sourceRaw : 'api';

$toBool = static function ($v): bool {
    if (is_bool($v)) {
        return $v;
    }
    if (!is_string($v) && !is_int($v) && !is_float($v)) {
        return false;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'on', 'yes'], true);
};

$toInt = static function ($v, int $default): int {
    if (!is_scalar($v)) {
        return $default;
    }
    $n = (int) $v;
    return $n > 0 ? $n : $default;
};

$defaultModuleEnabled = static function (): bool {
    $v = getenv('RATIB_INFRA_MARKETPLACE_ENABLED');
    if ($v === false || $v === '') {
        return false;
    }
    return !in_array(strtolower((string) $v), ['0', 'false', 'off', 'no'], true);
};

$fullControlForm = !empty($body['runtime_controls_submit']);

/**
 * @param array<string, mixed> $patch
 * @param array<string, mixed> $existing
 */
$applyProviderFlag = static function (string $bodyKey, string $provider, string $mode, array $body, array $patch, array $existing): array {
    if (!array_key_exists($bodyKey, $body)) {
        return $patch;
    }
    $raw = trim((string) $body[$bodyKey]);
    if ($raw === '' || strtolower($raw) === 'inherit') {
        $flags = $patch['provider_flags'] ?? ($existing['provider_flags'] ?? []);
        if (!is_array($flags)) {
            $flags = [];
        }
        $p = $flags[$provider] ?? [];
        if (!is_array($p)) {
            $p = [];
        }
        unset($p[$mode]);
        if ($p === []) {
            unset($flags[$provider]);
        } else {
            $flags[$provider] = $p;
        }
        $patch['provider_flags'] = $flags;

        return $patch;
    }
    if ($raw === '0' || strtolower($raw) === 'false' || strtolower($raw) === 'no') {
        $boolVal = false;
    } else {
        $boolVal = $raw === '1' || strtolower($raw) === 'true' || strtolower($raw) === 'yes';
    }
    $flags = $patch['provider_flags'] ?? ($existing['provider_flags'] ?? []);
    if (!is_array($flags)) {
        $flags = [];
    }
    $p = $flags[$provider] ?? [];
    if (!is_array($p)) {
        $p = [];
    }
    $p[$mode] = $boolVal;
    $flags[$provider] = $p;
    $patch['provider_flags'] = $flags;

    return $patch;
};

$existing = RuntimeOverrideStore::read();

/** @var array<string, mixed> $patch */
$patch = [];

if ($fullControlForm || array_key_exists('enabled', $body)) {
    if ($fullControlForm) {
        $patch['enabled'] = array_key_exists('enabled', $body) ? $toBool($body['enabled']) : ($existing['enabled'] ?? $defaultModuleEnabled());
    } else {
        $patch['enabled'] = $toBool($body['enabled']);
    }
}

if ($fullControlForm || array_key_exists('dry_run', $body)) {
    $patch['dry_run'] = $fullControlForm
        ? (array_key_exists('dry_run', $body) ? $toBool($body['dry_run']) : (bool) ($existing['dry_run'] ?? false))
        : $toBool($body['dry_run']);
}

if ($fullControlForm || array_key_exists('execution_kill_switch', $body)) {
    $patch['execution_kill_switch'] = $fullControlForm
        ? (array_key_exists('execution_kill_switch', $body) ? $toBool($body['execution_kill_switch']) : (bool) ($existing['execution_kill_switch'] ?? false))
        : $toBool($body['execution_kill_switch']);
}

if ($fullControlForm || array_key_exists('queue_driver', $body)) {
    $queueDriverRaw = isset($body['queue_driver']) ? strtolower(trim((string) $body['queue_driver'])) : '';
    if ($queueDriverRaw === '' && $fullControlForm) {
        $prev = $existing['queue_driver'] ?? null;
        $queueDriverRaw = is_string($prev) && $prev !== '' ? strtolower(trim($prev)) : 'sync';
    }
    $qd = in_array($queueDriverRaw, ['sync', 'database', 'redis'], true) ? $queueDriverRaw : 'sync';
    $patch['queue_driver'] = $qd;
}

if ($fullControlForm || array_key_exists('queue_max_attempts', $body)) {
    $patch['queue_max_attempts'] = $fullControlForm && !array_key_exists('queue_max_attempts', $body)
        ? (int) ($existing['queue_max_attempts'] ?? 5)
        : $toInt($body['queue_max_attempts'] ?? 5, 5);
}

if ($fullControlForm || array_key_exists('queue_pressure_threshold', $body)) {
    $patch['queue_pressure_threshold'] = $fullControlForm && !array_key_exists('queue_pressure_threshold', $body)
        ? max(100, (int) ($existing['queue_pressure_threshold'] ?? 2000))
        : max(100, $toInt($body['queue_pressure_threshold'] ?? 2000, 2000));
}

if ($fullControlForm || array_key_exists('worker_max_loop_jobs', $body)) {
    $patch['worker_max_loop_jobs'] = $fullControlForm && !array_key_exists('worker_max_loop_jobs', $body)
        ? (int) ($existing['worker_max_loop_jobs'] ?? 1000)
        : $toInt($body['worker_max_loop_jobs'] ?? 1000, 1000);
}

if ($fullControlForm || array_key_exists('default_currency', $body)) {
    $currencyRaw = isset($body['default_currency']) ? strtoupper(trim((string) $body['default_currency'])) : '';
    if ($currencyRaw === '' && $fullControlForm) {
        $currencyRaw = strtoupper(trim((string) ($existing['default_currency'] ?? 'USD')));
    }
    if ($currencyRaw === '') {
        $currencyRaw = 'USD';
    }
    $currency = preg_match('/^[A-Z]{3}$/', $currencyRaw) === 1 ? $currencyRaw : 'USD';
    $patch['default_currency'] = $currency;
}

if ($fullControlForm || array_key_exists('cpanel_base_url', $body)) {
    if ($fullControlForm && !array_key_exists('cpanel_base_url', $body)) {
        $patch['cpanel_base_url'] = (string) ($existing['cpanel_base_url'] ?? '');
    } else {
        $patch['cpanel_base_url'] = trim((string) ($body['cpanel_base_url'] ?? ''));
    }
}

if ($fullControlForm || array_key_exists('cpanel_username', $body)) {
    if ($fullControlForm && !array_key_exists('cpanel_username', $body)) {
        $patch['cpanel_username'] = (string) ($existing['cpanel_username'] ?? '');
    } else {
        $patch['cpanel_username'] = trim((string) ($body['cpanel_username'] ?? ''));
    }
}

if (array_key_exists('cpanel_api_token', $body)) {
    $v = trim((string) $body['cpanel_api_token']);
    if ($v !== '') {
        $patch['cpanel_api_token'] = $v;
    }
}

if ($fullControlForm || array_key_exists('tenant_allowlist', $body)) {
    if ($fullControlForm && !array_key_exists('tenant_allowlist', $body)) {
        $patch['tenant_allowlist'] = is_array($existing['tenant_allowlist'] ?? null) ? $existing['tenant_allowlist'] : [];
    } else {
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
        $patch['tenant_allowlist'] = $allow;
    }
}

$patch = $applyProviderFlag('pf_namecheap_live', 'namecheap', 'live', $body, $patch, $existing);
$patch = $applyProviderFlag('pf_namecheap_sandbox', 'namecheap', 'sandbox', $body, $patch, $existing);
$patch = $applyProviderFlag('pf_cloudflare_dns_live', 'cloudflare_dns', 'live', $body, $patch, $existing);
$patch = $applyProviderFlag('pf_cloudflare_dns_sandbox', 'cloudflare_dns', 'sandbox', $body, $patch, $existing);
$patch = $applyProviderFlag('pf_letsencrypt_ssl_live', 'letsencrypt_ssl', 'live', $body, $patch, $existing);
$patch = $applyProviderFlag('pf_letsencrypt_ssl_sandbox', 'letsencrypt_ssl', 'sandbox', $body, $patch, $existing);

if (array_key_exists('nc_api_user', $body) || array_key_exists('nc_api_key', $body)
    || array_key_exists('nc_username', $body) || array_key_exists('nc_client_ip', $body)) {
    $rs = is_array($existing['registrar_secrets'] ?? null) ? $existing['registrar_secrets'] : [];
    $nc = is_array($rs['namecheap'] ?? null) ? $rs['namecheap'] : [];
    if (array_key_exists('nc_api_user', $body)) {
        $v = trim((string) $body['nc_api_user']);
        if ($v === '') {
            unset($nc['api_user']);
        } else {
            $nc['api_user'] = $v;
        }
    }
    if (array_key_exists('nc_username', $body)) {
        $v = trim((string) $body['nc_username']);
        if ($v === '') {
            unset($nc['username']);
        } else {
            $nc['username'] = $v;
        }
    }
    if (array_key_exists('nc_client_ip', $body)) {
        $v = trim((string) $body['nc_client_ip']);
        if ($v === '') {
            unset($nc['client_ip']);
        } else {
            $nc['client_ip'] = $v;
        }
    }
    if (array_key_exists('nc_api_key', $body)) {
        $v = trim((string) $body['nc_api_key']);
        if ($v !== '') {
            $nc['api_key'] = $v;
        }
    }
    if ($nc === []) {
        unset($rs['namecheap']);
    } else {
        $rs['namecheap'] = $nc;
    }
    if ($rs === []) {
        $patch['registrar_secrets'] = [];
    } else {
        $patch['registrar_secrets'] = $rs;
    }
}

if ($patch === []) {
    $respond(422, ['ok' => false, 'message' => 'No recognized settings to update']);
}

$oldOverrides = [];
$newOverrides = [];
try {
    $updated = RuntimeOverrideStore::updateAtomic(
        /**
         * @param array<string, mixed> $ex
         * @return array<string, mixed>
         */
        static function (array $ex) use ($patch): array {
            return array_merge($ex, $patch);
        }
    );
    $oldOverrides = $updated['old'];
    $newOverrides = $updated['new'];
} catch (\Throwable $e) {
    $respond(500, [
        'ok' => false,
        'message' => 'Unable to write runtime overrides file',
        'hint' => 'Ensure storage/infrastructure-marketplace/ (or ratib_uploads) is writable by PHP, or set RATIB_INFRA_RUNTIME_OVERRIDES_PATH.',
        'target' => RuntimeOverrideStore::path(),
        'detail' => substr($e->getMessage(), 0, 240),
    ]);
}

/** @var array<string, array{old:mixed,new:mixed}> $changes */
$changes = [];
foreach (array_keys($patch) as $k) {
    $old = $oldOverrides[$k] ?? null;
    $new = $newOverrides[$k] ?? null;
    if (json_encode($old, JSON_UNESCAPED_SLASHES) !== json_encode($new, JSON_UNESCAPED_SLASHES)) {
        $changes[$k] = ['old' => $old, 'new' => $new];
    }
}

if (isset($changes['registrar_secrets'])) {
    $changes['registrar_secrets'] = [
        'old' => '[secrets redacted]',
        'new' => '[secrets redacted]',
    ];
}
if (isset($changes['cpanel_api_token'])) {
    $changes['cpanel_api_token'] = [
        'old' => '[secret redacted]',
        'new' => '[secret redacted]',
    ];
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

$sanitizedOut = $newOverrides;
if (isset($sanitizedOut['registrar_secrets'])) {
    $sanitizedOut['registrar_secrets'] = '[set via Control Panel — omitted from response]';
}
if (isset($sanitizedOut['cpanel_api_token'])) {
    $sanitizedOut['cpanel_api_token'] = '[set via Control Panel — omitted from response]';
}

$secretsSync = ['skipped' => true, 'reason' => 'not_attempted'];
$providerChecks = [];
try {
    InfraEnvBootstrap::load();
    $pdo = DatabaseConnectionFactory::createPdo();
    $secretsSync = (new InfraProviderSecretsSync($pdo))->syncFromRuntimeOverrides(
        $newOverrides,
        (string) ($_SESSION['control_username'] ?? 'control-update')
    );
    $diag = new ProviderDiagnosticsService($pdo);
    foreach ((array) ($diag->verify()['checks'] ?? []) as $check) {
        if (!is_array($check)) {
            continue;
        }
        $name = (string) ($check['name'] ?? '');
        if (!in_array($name, ['cpanel_connectivity', 'namecheap_reachability', 'cloudflare_connectivity'], true)) {
            continue;
        }
        $providerChecks[$name] = [
            'status' => (string) ($check['status'] ?? 'WARN'),
            'message' => (string) ($check['message'] ?? ''),
            'http_status' => $check['http_status'] ?? null,
        ];
    }
} catch (\Throwable $e) {
    $secretsSync = ['skipped' => true, 'reason' => substr($e->getMessage(), 0, 120)];
}

echo json_encode([
    'ok' => true,
    'message' => 'Infrastructure control settings updated',
    'file' => RuntimeOverrideStore::path(),
    'overrides' => $sanitizedOut,
    'changed_keys' => array_keys($patch),
    'secrets_sync' => $secretsSync,
    'provider_checks' => $providerChecks,
], JSON_UNESCAPED_SLASHES);
