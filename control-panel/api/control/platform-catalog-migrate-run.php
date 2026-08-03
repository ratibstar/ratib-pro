<?php

declare(strict_types=1);

/**
 * Control Panel — run RATEB Platform Catalog migrations.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/control/platform-catalog-bridge.php';

if (!control_platform_catalog_verify_migrate_token()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!control_platform_catalog_is_installed()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'catalog_not_installed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $log = control_platform_catalog_run_migrations();
    echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
