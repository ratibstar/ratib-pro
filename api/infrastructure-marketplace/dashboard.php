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
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Providers\Capabilities\CapabilityDiscoveryService;
use Ratib\InfrastructureMarketplace\Providers\Health\ProviderHealthService;
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
    $metrics = new InfrastructureMetrics(new InfrastructureEventEmitter());
    $jobs = new ProvisioningJobRepository($pdo);
    $counts = $jobs->statusCounts();
    $payload['queue']['depth'] = $jobs->queueDepth();
    $metrics->queueDepth($jobs->queueDepth());
    $metrics->queuePressure(min(1, $jobs->queueDepth() / 2000));
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

    $activations = new ProviderActivationRegistry($pdo);
    $providerHealth = new ProviderHealthService($activations, $metrics);
    $capabilities = new CapabilityDiscoveryService($activations);
    $payload['providers']['health_snapshot'] = $providerHealth->healthSnapshot(null, null);
    $payload['providers']['capabilities_hosting'] = $capabilities->discover('hosting', null, null);

    $orderRows = $pdo->query(
        'SELECT status, COUNT(*) c FROM ratib_infra_orders GROUP BY status'
    );
    $orderCounts = [];
    if ($orderRows instanceof PDOStatement) {
        while ($r = $orderRows->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($r)) {
                continue;
            }
            $s = (string) ($r['status'] ?? '');
            $c = (int) ($r['c'] ?? 0);
            $orderCounts[$s] = $c;
            $metrics->orderConversionMetric($s, $c);
        }
    }
    $payload['traces']['order_counts'] = $orderCounts;

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

try {
    (new InfrastructureEventEmitter())->structuredLog('info', 'Infrastructure dashboard snapshot generated', [
        'queue_driver' => ModuleConfig::defaultQueueDriver(),
    ]);
} catch (\Throwable $e) {
    $payload['diagnostics']['eventbus'] = 'eventbus_unavailable';
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES);

