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

use Ratib\InfrastructureMarketplace\Domains\Search\DomainSearchCache;
use Ratib\InfrastructureMarketplace\Domains\Search\DomainSearchRateLimiter;
use Ratib\InfrastructureMarketplace\Domains\Search\DomainSearchService;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Registrars\Search\RegistrarSearchAggregator;

try {
    $keyword = trim((string) ($_GET['q'] ?? ''));
    if ($keyword === '') {
        throw new RuntimeException('q required');
    }
    $tlds = isset($_GET['tlds']) ? explode(',', (string) $_GET['tlds']) : ['com', 'net', 'org'];
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : null;
    $pdo = DatabaseConnectionFactory::createPdo();
    $scope = 'public:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limiter = new DomainSearchRateLimiter($pdo);
    if (!$limiter->allow($scope, 30)) {
        throw new RuntimeException('rate_limited');
    }
    $service = new DomainSearchService(
        new DomainSearchCache($pdo),
        new RegistrarSearchAggregator(new ProviderActivationRegistry($pdo)),
        new InfrastructureMetrics(new InfrastructureEventEmitter())
    );
    echo json_encode(['ok' => true, 'items' => $service->search($keyword, $tlds, new TenantContext($tenantId, $agencyId))], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Domain search unavailable'], JSON_UNESCAPED_SLASHES);
}

