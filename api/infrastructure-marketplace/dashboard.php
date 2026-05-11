<?php
declare(strict_types=1);

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

require_once dirname(__DIR__, 2) . '/modules/infrastructure-marketplace/bootstrap.php';

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

$payload = [
    'health' => [
        'module_enabled' => ModuleConfig::isModuleEnabled(),
        'queue_driver' => ModuleConfig::defaultQueueDriver(),
    ],
    'queue' => [
        'driver' => ModuleConfig::defaultQueueDriver(),
        'max_attempts' => ModuleConfig::queueMaxAttempts(),
        'dead_state' => ModuleConfig::queueDeadLetterState(),
    ],
    'providers' => [
        'cpanel_whm_base_url_configured' => ModuleConfig::cpanelWhmBaseUrl() !== null,
        'bindings_defined' => count(ModuleConfig::providerBindings()),
        'cpanel_username_masked' => SecretManager::masked(ModuleConfig::cpanelWhmUsername()),
    ],
    'catalog' => [
        'status' => 'catalog repository ready',
    ],
    'jobs' => [
        'status' => 'job repository ready',
    ],
    'workers' => [
        'status' => 'heartbeat table not queried',
    ],
    'failed' => [
        'status' => 'not queried',
    ],
    'reconciliation' => [
        'status' => 'not queried',
    ],
    'diagnostics' => [
        'status' => 'provider diagnostics',
        'cpanel_dependency' => ModuleConfig::cpanelWhmBaseUrl() !== null ? 'configured' : 'not_configured',
    ],
    'traces' => [
        'status' => 'trace query not executed',
    ],
    'audit' => [
        'status' => 'audit query not executed',
    ],
];

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $jobs = new ProvisioningJobRepository($pdo);
    $counts = $jobs->statusCounts();
    $payload['queue']['depth'] = $jobs->queueDepth();
    $payload['jobs'] = $counts;
    $payload['failed'] = [
        'failed' => (int) ($counts['FAILED'] ?? 0),
        'dead_letter' => (int) ($counts['DEAD_LETTER'] ?? 0),
    ];
    $payload['reconciliation'] = [
        'required' => (int) (($counts['RECONCILING'] ?? 0) + ($counts['DEAD_LETTER'] ?? 0)),
    ];

    $workerRows = $pdo->query(
        'SELECT worker_name, heartbeat_at, memory_bytes
         FROM ratib_infra_worker_heartbeats
         ORDER BY heartbeat_at DESC
         LIMIT 5'
    );
    $workers = [];
    if ($workerRows instanceof PDOStatement) {
        while ($r = $workerRows->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($r)) {
                continue;
            }
            $workers[(string) $r['worker_name']] = (string) $r['heartbeat_at'] . ' | memory=' . (string) $r['memory_bytes'];
        }
    }
    $payload['workers'] = $workers === [] ? ['status' => 'no workers reporting'] : $workers;

    $auditRows = $pdo->query(
        'SELECT action_type, created_at
         FROM ratib_infra_audit_entries
         ORDER BY id DESC
         LIMIT 5'
    );
    $audit = [];
    if ($auditRows instanceof PDOStatement) {
        while ($r = $auditRows->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($r)) {
                continue;
            }
            $audit[] = (string) $r['created_at'] . ' ' . (string) $r['action_type'];
        }
    }
    $payload['audit'] = ['latest' => implode("\n", $audit)];
} catch (\Throwable $e) {
    $payload['diagnostics']['db'] = 'Dashboard DB query unavailable';
}

(new InfrastructureEventEmitter())->structuredLog('info', 'Infrastructure dashboard snapshot generated', [
    'queue_driver' => ModuleConfig::defaultQueueDriver(),
]);

echo json_encode($payload, JSON_UNESCAPED_SLASHES);

