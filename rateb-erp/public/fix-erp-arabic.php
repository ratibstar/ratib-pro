<?php
declare(strict_types=1);

/**
 * One-time ERP Arabic repair (permissions + demo data). Same token as run-migrations.php.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ratebRoot = realpath(__DIR__ . '/..');
define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot !== false ? $ratebRoot : dirname(__DIR__)));

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden — send header X-Rateb-Migrate-Token\n");
}

$expected = '';
$tokenFile = RATEB_ROOT . '/storage/deploy-migrate-token';
if (!is_file($tokenFile)) {
    $tokenFile = RATEB_ROOT . '/storage/.deploy-migrate-token';
}
if (is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));
}
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden — invalid token\n");
}

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$result = (new Rateb\App\Services\ErpArabicRepairService())->repair();
echo "ERP Arabic repair complete. Rows touched: {$result['updated']}\n";
echo 'dashboard.view name_ar: ' . $result['permissions_sample'] . "\n";
