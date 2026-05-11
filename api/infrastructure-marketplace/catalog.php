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

use Ratib\InfrastructureMarketplace\Catalog\API\CatalogController;
use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('catalog', ControlSecurityGuard::TIER_PUBLIC_READ);

try {
    $pdo = DatabaseConnectionFactory::createPdo();
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : null;
    $currency = isset($_GET['currency']) ? (string) $_GET['currency'] : ModuleConfig::defaultMarketplaceCurrency();
    $controller = new CatalogController($pdo);
    echo json_encode($controller->index($tenantId, $agencyId, $currency), JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'currency' => isset($currency) ? strtoupper((string) $currency) : ModuleConfig::defaultMarketplaceCurrency(),
        'items' => [],
        'warning' => 'catalog_source_unavailable',
    ], JSON_UNESCAPED_SLASHES);
}

