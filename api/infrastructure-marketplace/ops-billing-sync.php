<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

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

use RATEB\InfrastructureMarketplace\Billing\InfrastructureBillingMetadataBridge;
use RATEB\InfrastructureMarketplace\Billing\InfrastructureBillingSynchronizer;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('ops-billing-sync', ControlSecurityGuard::TIER_CONTROL_VIEW);

try {
    $publicId = (string) ($_GET['order_public_id'] ?? '');
    if ($publicId === '') {
        throw new RuntimeException('order_public_id required');
    }
    $pdo = DatabaseConnectionFactory::createPdo();
    $sync = new InfrastructureBillingSynchronizer($pdo, new InfrastructureBillingMetadataBridge());
    echo json_encode($sync->buildProvisioningInvoiceLink($publicId), JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Billing sync unavailable'], JSON_UNESCAPED_SLASHES);
}

