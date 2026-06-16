<?php
/**
 * Thin API entry for infrastructure marketplace module (foundation).
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

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

use RATEB\InfrastructureMarketplace\Controllers\HealthController;
use RATEB\InfrastructureMarketplace\Security\ControlSecurityGuard;

ControlSecurityGuard::enforce('health', ControlSecurityGuard::TIER_PUBLIC_READ);

echo json_encode(HealthController::handle(), JSON_UNESCAPED_SLASHES);
