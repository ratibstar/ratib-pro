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
    ],
    'catalog' => [
        'status' => 'catalog repository ready',
    ],
    'jobs' => [
        'status' => 'job repository ready',
    ],
];

(new InfrastructureEventEmitter())->structuredLog('info', 'Infrastructure dashboard snapshot generated', [
    'queue_driver' => ModuleConfig::defaultQueueDriver(),
]);

echo json_encode($payload, JSON_UNESCAPED_SLASHES);

