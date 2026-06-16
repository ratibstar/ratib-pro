<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;
use RATEB\InfrastructureMarketplace\Observability\ProviderEventLogger;
use RATEB\InfrastructureMarketplace\Security\Secrets\ProviderSecretCipher;

$checks = [];

try {
    $pdo = DatabaseConnectionFactory::createPdo();
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'checks' => [['name' => 'db_connection', 'status' => 'FAIL', 'detail' => 'Database unavailable']]], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$requiredTables = [
    'rateb_infra_provider_activations',
    'rateb_infra_provider_secrets',
    'rateb_infra_provider_events',
    'rateb_infra_audit_entries',
];
foreach ($requiredTables as $table) {
    $exists = SchemaHelpers::tableExists($pdo, $table);
    $checks[] = [
        'name' => 'table_' . $table,
        'status' => $exists ? 'PASS' : 'FAIL',
        'detail' => $exists ? 'present' : 'missing',
    ];
}

$indexOk = false;
if (SchemaHelpers::tableExists($pdo, 'rateb_infra_provider_events')) {
    $idx = $pdo->query("SHOW INDEX FROM rateb_infra_provider_events WHERE Key_name = 'idx_rateb_infra_provider_events_provider'");
    $indexOk = $idx instanceof \PDOStatement && $idx->fetch(\PDO::FETCH_ASSOC) !== false;
}
$checks[] = ['name' => 'provider_events_indexes', 'status' => $indexOk ? 'PASS' : 'WARN', 'detail' => $indexOk ? 'ok' : 'expected index missing'];

InfraEnvBootstrap::ensureSecretKeyProvisioned();
InfraEnvBootstrap::load();

$encryptOk = false;
$encryptDetail = 'skipped_no_key';
if (InfraEnvBootstrap::hasSecretKey()) {
    try {
        $cipher = new ProviderSecretCipher();
        $enc = $cipher->encrypt('verify-' . bin2hex(random_bytes(4)));
        $plain = $cipher->decrypt($enc);
        $encryptOk = is_string($plain) && strpos($plain, 'verify-') === 0;
        $encryptDetail = $encryptOk ? 'roundtrip_ok' : 'roundtrip_failed';
    } catch (\Throwable $e) {
        $encryptDetail = substr($e->getMessage(), 0, 120);
    }
}
$checks[] = ['name' => 'secret_encryption', 'status' => $encryptOk ? 'PASS' : 'WARN', 'detail' => $encryptDetail];

$eventsWriteOk = false;
if (SchemaHelpers::tableExists($pdo, 'rateb_infra_provider_events')) {
    try {
        $logger = new ProviderEventLogger($pdo);
        $rid = 'verify-' . bin2hex(random_bytes(4));
        $logger->log('orchestration', 'production_verify', 'health_check', [
            'request_id' => $rid,
            'operation_name' => 'production_verify',
            'status' => 'success',
            'duration_ms' => 1,
            'payload' => ['probe' => true],
        ]);
        $stmt = $pdo->prepare('SELECT id FROM rateb_infra_provider_events WHERE request_id = :rid LIMIT 1');
        $stmt->execute(['rid' => $rid]);
        $eventsWriteOk = $stmt->fetchColumn() !== false;
        if ($eventsWriteOk) {
            $pdo->prepare('DELETE FROM rateb_infra_provider_events WHERE request_id = :rid')->execute(['rid' => $rid]);
        }
    } catch (\Throwable $e) {
        $eventsWriteOk = false;
    }
}
$checks[] = ['name' => 'provider_events_write', 'status' => $eventsWriteOk ? 'PASS' : 'FAIL', 'detail' => $eventsWriteOk ? 'ok' : 'insert_failed'];

$plaintextLeak = false;
if (SchemaHelpers::tableExists($pdo, 'rateb_infra_provider_secrets')) {
    $stmt = $pdo->query('SELECT encrypted_value FROM rateb_infra_provider_secrets ORDER BY id DESC LIMIT 5');
    if ($stmt instanceof \PDOStatement) {
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $val = (string) ($row['encrypted_value'] ?? '');
            if ($val !== '' && preg_match('/^[A-Za-z0-9+\/=_-]{8,}$/', $val) === 1 && strpos($val, '{') === false && strpos($val, 'eyJ') !== 0) {
                // Heuristic: long alphanumeric without JSON envelope may be plaintext.
                if (strlen($val) < 40 || base64_decode($val, true) === false) {
                    $plaintextLeak = true;
                    break;
                }
            }
        }
    }
}
$checks[] = ['name' => 'no_plaintext_secrets_sample', 'status' => $plaintextLeak ? 'FAIL' : 'PASS', 'detail' => $plaintextLeak ? 'possible_plaintext_detected' : 'sample_ok'];

$healthMonitorOk = is_file(dirname(__DIR__) . '/Cli/provider-health-monitor.php');
$checks[] = ['name' => 'health_monitor_script', 'status' => $healthMonitorOk ? 'PASS' : 'FAIL', 'detail' => $healthMonitorOk ? 'present' : 'missing'];

$retentionOk = is_file(dirname(__DIR__) . '/Cli/provider-events-retention.php');
$checks[] = ['name' => 'retention_script', 'status' => $retentionOk ? 'PASS' : 'WARN', 'detail' => $retentionOk ? 'present' : 'missing'];

$fail = 0;
$warn = 0;
foreach ($checks as $c) {
    if (($c['status'] ?? '') === 'FAIL') {
        $fail++;
    } elseif (($c['status'] ?? '') === 'WARN') {
        $warn++;
    }
}
$overall = $fail > 0 ? 'FAIL' : ($warn > 0 ? 'WARN' : 'PASS');

echo json_encode([
    'ok' => $fail === 0,
    'overall' => $overall,
    'fail_count' => $fail,
    'warn_count' => $warn,
    'checks' => $checks,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($fail > 0 ? 1 : 0);
