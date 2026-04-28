<?php
/**
 * EN: Handles API endpoint/business logic in `api/tenants/create.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/tenants/create.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../models/Tenant.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ob_clean();
    echo ApiResponse::error('Method not allowed', 405);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $data = is_array($jsonData) ? $jsonData : $_POST;

    $name = trim((string) ($data['name'] ?? ''));
    $domain = strtolower(trim((string) ($data['domain'] ?? '')));

    if ($name === '' || $domain === '') {
        ob_clean();
        echo ApiResponse::error('name and domain are required');
        exit;
    }

    // Basic domain format validation for safe canonical storage.
    $domainIsValid = (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    if (!$domainIsValid) {
        ob_clean();
        echo ApiResponse::error('Invalid domain format');
        exit;
    }

    $existing = Tenant::findByDomain($domain);
    if ($existing) {
        ob_clean();
        echo ApiResponse::error('Domain already exists', 409);
        exit;
    }

    $tenantId = Tenant::createTenant([
        'name' => $name,
        'domain' => $domain,
        'database_name' => '',
        'db_host' => null,
        'db_user' => '',
        'db_password' => '',
        'status' => 'provisioning',
    ]);

    ob_clean();
    echo ApiResponse::success(
        ['tenant_id' => $tenantId],
        'Tenant created with provisioning status'
    );
    exit;
} catch (Throwable $e) {
    error_log('Tenant create API error: ' . $e->getMessage());
    ob_clean();
    echo ApiResponse::error('Failed to create tenant');
    exit;
}
