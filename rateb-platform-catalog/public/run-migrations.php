<?php

declare(strict_types=1);

/**
 * Token-gated catalog migrations (same pattern as ERP migrate tools).
 * Header: X-Rateb-Migrate-Token
 */
$root = realpath(dirname(__DIR__));
if ($root === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'invalid_root'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$token = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? $_GET['token'] ?? ''));
$expected = '';
foreach ([
    $root . '/storage/deploy-migrate-token',
    $root . '/storage/.deploy-migrate-token',
    dirname($root) . '/rateb-erp/storage/deploy-migrate-token',
    dirname($root) . '/rateb-erp/storage/.deploy-migrate-token',
] as $file) {
    if (is_file($file)) {
        $expected = trim((string) file_get_contents($file));
        if ($expected !== '') {
            break;
        }
    }
}
if ($expected === '') {
    $env = getenv('RATEB_ERP_MIGRATE_TOKEN');
    if (is_string($env) && trim($env) !== '') {
        $expected = trim($env);
    }
}
if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!defined('RATEB_CATALOG_NO_SESSION')) {
    define('RATEB_CATALOG_NO_SESSION', true);
}
if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($root);

try {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $log = (new \Rateb\PlatformCatalog\Application\Services\MigrationService())->runAll();
    echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
