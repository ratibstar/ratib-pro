<?php
/**
 * EN: Handles API endpoint/business logic in `api/tenants/create-full.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/tenants/create-full.php`.
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

/**
 * Create a dedicated PDO connection to the control DB.
 */
function tenantFlowControlPdo(): PDO
{
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== ''
        ? CONTROL_PANEL_DB_NAME
        : (defined('DB_NAME') ? DB_NAME : '');

    if ($user === '' || $dbName === '') {
        throw new RuntimeException('Control DB configuration missing.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Generate safe MySQL database name (max 64 chars).
 */
function tenantFlowDatabaseName(string $domain): string
{
    $slug = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($domain));
    $slug = trim((string) $slug, '_');
    if ($slug === '') {
        $slug = 'tenant';
    }
    $slug = substr($slug, 0, 40);
    return 'tenant_' . $slug . '_' . date('ymdHis');
}

/**
 * Basic domain routing verification (no DNS API integration):
 * - Domain must resolve (A lookup via gethostbyname).
 * - If current request host resolves, new domain IP must match current host IP.
 */
function tenantFlowValidateDomainRouting(string $domain): array
{
    $resolvedDomainIp = gethostbyname($domain);
    if ($resolvedDomainIp === $domain) {
        return [
            'ok' => false,
            'reason' => 'Domain does not resolve to an IP',
            'domain_ip' => null,
            'current_host_ip' => null,
        ];
    }

    $currentHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($currentHost !== '' && strpos($currentHost, ':') !== false) {
        $currentHost = explode(':', $currentHost, 2)[0];
    }

    $currentHostIp = null;
    if ($currentHost !== '') {
        $resolvedCurrent = gethostbyname($currentHost);
        if ($resolvedCurrent !== $currentHost) {
            $currentHostIp = $resolvedCurrent;
        }
    }

    if ($currentHostIp !== null && $resolvedDomainIp !== $currentHostIp) {
        return [
            'ok' => false,
            'reason' => 'Domain resolves to different target',
            'domain_ip' => $resolvedDomainIp,
            'current_host_ip' => $currentHostIp,
        ];
    }

    return [
        'ok' => true,
        'reason' => 'Domain routing verified',
        'domain_ip' => $resolvedDomainIp,
        'current_host_ip' => $currentHostIp,
    ];
}

try {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    $data = is_array($jsonData) ? $jsonData : $_POST;

    $name = trim((string) ($data['name'] ?? $data['agency_name'] ?? ''));
    $domain = strtolower(trim((string) ($data['domain'] ?? '')));

    if ($name === '' || $domain === '') {
        ob_clean();
        echo ApiResponse::error('agency name and domain are required');
        exit;
    }

    $domainIsValid = (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    if (!$domainIsValid) {
        ob_clean();
        echo ApiResponse::error('Invalid domain format');
        exit;
    }

    error_log('TENANT_CREATE_FULL step=start domain=' . $domain);

    $existing = Tenant::findByDomain($domain);
    if ($existing) {
        ob_clean();
        echo ApiResponse::error('Domain already exists', 409);
        exit;
    }

    $controlPdo = tenantFlowControlPdo();
    $tenantId = 0;
    $dbCreated = false;
    $databaseName = '';

    try {
        // Step 1: create provisioning tenant record
        $tenantId = Tenant::createTenant([
            'name' => $name,
            'domain' => $domain,
            'database_name' => '',
            'db_host' => null,
            'db_user' => '',
            'db_password' => '',
            'status' => 'provisioning',
        ]);
        error_log('TENANT_CREATE_FULL step=tenant_created tenant_id=' . $tenantId . ' domain=' . $domain);

        // Step 2: create tenant database (simple version)
        $databaseName = tenantFlowDatabaseName($domain);
        $createDbSql = "CREATE DATABASE `" . str_replace('`', '``', $databaseName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $controlPdo->exec($createDbSql);
        $dbCreated = true;
        error_log('TENANT_CREATE_FULL step=db_created tenant_id=' . $tenantId . ' db=' . $databaseName);

        // Step 3: domain routing validation BEFORE activation.
        $domainValidation = tenantFlowValidateDomainRouting($domain);
        if (empty($domainValidation['ok'])) {
            $reason = (string) ($domainValidation['reason'] ?? 'Domain validation failed');
            $domainIp = (string) ($domainValidation['domain_ip'] ?? '');
            $currentIp = (string) ($domainValidation['current_host_ip'] ?? '');
            error_log(
                'TENANT_CREATE_FULL step=domain_validation_failed tenant_id=' . $tenantId .
                ' domain=' . $domain . ' reason=' . $reason .
                ' domain_ip=' . $domainIp . ' current_host_ip=' . $currentIp
            );
            throw new RuntimeException('Domain routing validation failed: ' . $reason);
        }
        error_log(
            'TENANT_CREATE_FULL step=domain_validation_ok tenant_id=' . $tenantId .
            ' domain=' . $domain . ' domain_ip=' . (string) ($domainValidation['domain_ip'] ?? '')
        );

        // Step 4: assign credentials and activate (simple: reuse app DB credentials for now)
        $dbHost = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $dbUser = defined('DB_USER') ? (string) DB_USER : '';
        $dbPass = defined('DB_PASS') ? (string) DB_PASS : '';

        $stmt = $controlPdo->prepare(
            "UPDATE tenants
             SET database_name = :database_name,
                 db_host = :db_host,
                 db_user = :db_user,
                 db_password = :db_password,
                 status = 'active',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            ':database_name' => $databaseName,
            ':db_host' => $dbHost,
            ':db_user' => $dbUser,
            ':db_password' => $dbPass,
            ':id' => $tenantId,
        ]);
        error_log('TENANT_CREATE_FULL step=tenant_activated tenant_id=' . $tenantId . ' db=' . $databaseName);

        ob_clean();
        echo ApiResponse::success([
            'tenant_id' => $tenantId,
            'database_name' => $databaseName,
            'status' => 'active',
        ], 'Tenant created and activated successfully');
        exit;
    } catch (Throwable $flowError) {
        error_log('TENANT_CREATE_FULL step=error tenant_id=' . $tenantId . ' message=' . $flowError->getMessage());

        // Compensating rollback: drop created DB, then remove tenant row.
        if ($dbCreated && $databaseName !== '') {
            try {
                $dropDbSql = "DROP DATABASE IF EXISTS `" . str_replace('`', '``', $databaseName) . "`";
                $controlPdo->exec($dropDbSql);
                error_log('TENANT_CREATE_FULL step=rollback_db_dropped tenant_id=' . $tenantId . ' db=' . $databaseName);
            } catch (Throwable $dropErr) {
                error_log('TENANT_CREATE_FULL step=rollback_db_drop_failed tenant_id=' . $tenantId . ' message=' . $dropErr->getMessage());
            }
        }

        if ($tenantId > 0) {
            try {
                $deleteStmt = $controlPdo->prepare('DELETE FROM tenants WHERE id = :id');
                $deleteStmt->execute([':id' => $tenantId]);
                error_log('TENANT_CREATE_FULL step=rollback_tenant_deleted tenant_id=' . $tenantId);
            } catch (Throwable $deleteErr) {
                error_log('TENANT_CREATE_FULL step=rollback_tenant_delete_failed tenant_id=' . $tenantId . ' message=' . $deleteErr->getMessage());
            }
        }

        throw $flowError;
    }
} catch (Throwable $e) {
    error_log('TENANT_CREATE_FULL failed message=' . $e->getMessage());
    ob_clean();
    echo ApiResponse::error('Failed to create tenant fully', 500);
    exit;
}

