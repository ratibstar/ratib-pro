<?php
declare(strict_types=1);

/**
 * Deploy/automation endpoint — runs pending ERP migrations on the server.
 * Auth: X-Rateb-Migrate-Token header must match storage/.deploy-migrate-token or RATEB_ERP_MIGRATE_TOKEN env.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

define('RATEB_ROOT', dirname(__DIR__));
define('RATEB_MIGRATE_ALLOWED', true);

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden\n");
}

$expected = '';
$tokenFile = RATEB_ROOT . '/storage/.deploy-migrate-token';
if (is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));
}
if ($expected === '') {
    $fromEnv = getenv('RATEB_ERP_MIGRATE_TOKEN');
    if ($fromEnv !== false && $fromEnv !== '') {
        $expected = (string) $fromEnv;
    }
}
if ($expected === '') {
    $cpEnv = dirname(RATEB_ROOT) . '/control-panel/config/env.php';
    if (is_file($cpEnv)) {
        require_once $cpEnv;
        if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
            $expected = (string) RATEB_ERP_MIGRATE_TOKEN;
        }
    }
}

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';

try {
    foreach ((new \Rateb\App\Services\ErpDatabaseService())->fixErpDatabase() as $line) {
        echo $line, "\n";
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ', $e->getMessage(), "\n";
}
