<?php

declare(strict_types=1);

/**
 * Deploy/automation — run Platform Catalog migrations on the server.
 * Auth: X-Rateb-Migrate-Token must match RATEB_ERP_MIGRATE_TOKEN / CPANEL_API_TOKEN / deploy token file.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$catalogRoot = realpath(dirname(__DIR__));
if ($catalogRoot === false) {
    http_response_code(500);
    exit("ERROR: invalid catalog root\n");
}
$catalogRoot = str_replace('\\', '/', $catalogRoot);

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden\n");
}

$expected = '';
$tokenCandidates = [
    $catalogRoot . '/storage/deploy-migrate-token',
    $catalogRoot . '/storage/.deploy-migrate-token',
    dirname($catalogRoot) . '/rateb-erp/storage/deploy-migrate-token',
    dirname($catalogRoot) . '/rateb-erp/storage/.deploy-migrate-token',
];
foreach ($tokenCandidates as $tokenFile) {
    if (is_file($tokenFile)) {
        $expected = trim((string) file_get_contents($tokenFile));
        if ($expected !== '') {
            break;
        }
    }
}
if ($expected === '') {
    foreach (['RATEB_ERP_MIGRATE_TOKEN', 'CPANEL_API_TOKEN'] as $envKey) {
        $fromEnv = getenv($envKey);
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            $expected = trim((string) $fromEnv);
            break;
        }
    }
}

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once $catalogRoot . '/app/Core/Bootstrap.php';
\Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($catalogRoot);

try {
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $lines = (new \Rateb\PlatformCatalog\Application\Services\MigrationService())->runAll();
    foreach ($lines as $line) {
        echo $line, "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ', $e->getMessage(), "\n";
}
