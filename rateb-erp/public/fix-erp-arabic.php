<?php
declare(strict_types=1);

/**
 * ERP Arabic repair — permissions, COA, demo data.
 * Auth: X-Rateb-Migrate-Token header OR logged-in super admin in browser.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$ratebRoot = realpath(__DIR__ . '/..');
define('RATEB_ROOT', str_replace('\\', '/', $ratebRoot !== false ? $ratebRoot : dirname(__DIR__)));

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
$expected = '';
$tokenFile = RATEB_ROOT . '/storage/deploy-migrate-token';
if (!is_file($tokenFile)) {
    $tokenFile = RATEB_ROOT . '/storage/.deploy-migrate-token';
}
if (is_file($tokenFile)) {
    $expected = trim((string) file_get_contents($tokenFile));
}

$tokenOk = $expected !== '' && $provided !== '' && hash_equals($expected, $provided);

require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

$sessionOk = \Rateb\App\Core\Auth::check()
    && !empty($_SESSION['rateb_is_super_admin']);

if (!$tokenOk && !$sessionOk) {
    http_response_code(403);
    if ($provided === '') {
        exit("Forbidden — log in as super admin, or send header X-Rateb-Migrate-Token\n");
    }
    exit("Forbidden — invalid token\n");
}

$result = (new Rateb\App\Services\ErpArabicRepairService())->repair();
echo "ERP Arabic repair complete. Rows touched: {$result['updated']}\n";
echo 'dashboard.view name_ar: ' . $result['permissions_sample'] . "\n";
