<?php
/**
 * EN: Handles API endpoint/business logic in `api/tenants/self-test.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/tenants/self-test.php`.
 */
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Endpoint classification: tenant context is mandatory.
define('TENANT_REQUIRED', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../api/core/TenantDatabaseManager.php';

try {
    if (!function_exists('getCurrentTenantId')) {
        require_once __DIR__ . '/../../includes/helpers/get_tenant_db.php';
    }

    $tenantId = getCurrentTenantId(); // throws if missing
    $tenantPdo = TenantDatabaseManager::pdoForTenantId($tenantId);

    // Connectivity check
    $stmt = $tenantPdo->query('SELECT 1');
    $probe = $stmt ? $stmt->fetchColumn() : false;
    if ((string) $probe !== '1') {
        throw new RuntimeException('Tenant DB probe failed.');
    }

    $dbName = (string) $tenantPdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') {
        throw new RuntimeException('Unable to resolve tenant database name.');
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'tenant_id' => $tenantId,
        'database_name' => $dbName,
        'connection_status' => 'OK',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    error_log('TENANT_SELF_TEST_FAIL endpoint=/api/tenants/self-test.php message=' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Tenant self-test failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

