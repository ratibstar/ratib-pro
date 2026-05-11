<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * @param array<string, mixed> $payload
 */
$emitJson = static function (array $payload): void {
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        $encoded = '{"ok":false,"message":"json_encoding_failed"}';
    }
    echo $encoded;
};

$__dashboardPayloadFallback = [
    'health' => ['module_enabled' => false, 'queue_driver' => 'unknown'],
    'queue' => ['driver' => 'unknown'],
    'providers' => ['status' => 'unavailable'],
    'jobs' => ['status' => 'unavailable'],
    'diagnostics' => ['status' => 'runtime_fallback'],
];

register_shutdown_function(static function () use (&$__dashboardPayloadFallback, $emitJson): void {
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($e['type'] ?? 0), $fatal, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
    }
    $emitJson(array_merge($__dashboardPayloadFallback, [
        'diagnostics' => array_merge((array)($__dashboardPayloadFallback['diagnostics'] ?? []), [
            'fatal_recovered' => true,
        ]),
    ]));
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    $emitJson(['ok' => false, 'message' => 'Method not allowed']);
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
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

ControlSecurityGuard::enforce('dashboard', ControlSecurityGuard::TIER_PUBLIC_READ);

$payload = [
    'health' => [
        'module_enabled' => ModuleConfig::isModuleEnabled(),
        'queue_driver' => ModuleConfig::defaultQueueDriver(),
    ],
    'queue' => [
        'driver' => ModuleConfig::defaultQueueDriver(),
        'max_attempts' => ModuleConfig::queueMaxAttempts(),
        'dead_state' => ModuleConfig::queueDeadLetterState(),
        'depth' => 0,
    ],
    'providers' => [
        'status' => count(ModuleConfig::providerBindings()) > 0 ? 'configured' : 'unavailable',
        'cpanel_whm_base_url_configured' => ModuleConfig::cpanelWhmBaseUrl() !== null,
        'bindings_defined' => count(ModuleConfig::providerBindings()),
        'cpanel_username_masked' => SecretManager::masked(ModuleConfig::cpanelWhmUsername()),
    ],
    'catalog' => [
        'status' => 'catalog repository ready',
    ],
    'jobs' => [
        'status' => 'job repository ready',
        'QUEUED' => 0,
        'RUNNING' => 0,
        'COMPLETED' => 0,
        'FAILED' => 0,
        'DEAD_LETTER' => 0,
    ],
    'workers' => [
        'status' => 'heartbeat table not queried',
    ],
    'failed' => [
        'status' => 'not queried',
        'failed' => 0,
        'dead_letter' => 0,
    ],
    'reconciliation' => [
        'status' => 'not queried',
    ],
    'diagnostics' => [
        'status' => 'provider diagnostics',
        'cpanel_dependency' => ModuleConfig::cpanelWhmBaseUrl() !== null ? 'configured' : 'not_configured',
        'db_reachable' => false,
    ],
    'traces' => [
        'status' => 'trace query not executed',
    ],
    'audit' => [
        'status' => 'audit query not executed',
    ],
];
$__dashboardPayloadFallback = $payload;

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $payload['diagnostics']['db_reachable'] = true;
    $metrics = new InfrastructureMetrics(new InfrastructureEventEmitter());
    $jobs = new ProvisioningJobRepository($pdo);
    $counts = $jobs->statusCounts();
    $queueDepth = $jobs->queueDepth();
    $payload['queue']['depth'] = $queueDepth;
    $metrics->queueDepth($queueDepth);
    $metrics->queuePressure((float) min(1, $queueDepth / 2000));
    $payload['jobs'] = $counts;
    $payload['failed'] = [
        'failed' => (int) ($counts['FAILED'] ?? 0),
        'dead_letter' => (int) ($counts['DEAD_LETTER'] ?? 0),
    ];
    $payload['reconciliation'] = [
        'required' => (int) (($counts['RECONCILING'] ?? 0) + ($counts['DEAD_LETTER'] ?? 0)),
    ];

    try {
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
    } catch (\Throwable $e) {
        $payload['workers'] = ['status' => 'worker table unavailable'];
    }

    try {
        $activations = new ProviderActivationRegistry($pdo);
        $providerHealth = new ProviderHealthService($activations, $metrics);
        $capabilities = new CapabilityDiscoveryService($activations);
        $payload['providers']['health_snapshot'] = $providerHealth->healthSnapshot(null, null);
        $payload['providers']['capabilities_hosting'] = $capabilities->discover('hosting', null, null);
        $payload['providers']['status'] = 'ready';
    } catch (\Throwable $e) {
        $payload['providers']['status'] = 'configured';
    }

    try {
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
    } catch (\Throwable $e) {
        $payload['traces']['order_counts'] = [];
    }

    try {
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
        $payload['audit'] = ['latest' => ''];
    }
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

$emitJson($payload);

