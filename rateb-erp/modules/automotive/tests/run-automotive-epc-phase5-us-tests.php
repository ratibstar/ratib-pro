<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveGate.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveUsImportService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveUsCatalogService.php';
require_once $root . '/app/Core/TenantContext.php';

use Rateb\App\Automotive\Services\AutomotiveUsCatalogService;
use Rateb\App\Automotive\Services\AutomotiveUsImportService;
use Rateb\App\Core\TenantContext;

$allow = true;
if (!function_exists('rateb_can')) {
    function rateb_can(string $slug): bool
    {
        global $allow;
        return $allow;
    }
}
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return $key;
    }
}
if (!function_exists('rateb_is_rtl')) {
    function rateb_is_rtl(): bool
    {
        return true;
    }
}

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0' || !str_contains((string) getenv('RATEB_EPC_DSN'), 'dbname=admin_rateb_epc_phase0')) {
    echo "NOT RUN: admin_rateb_epc_phase0 required\n";
    exit(2);
}
$pdo = new PDO((string) getenv('RATEB_EPC_DSN'), (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'admin_rateb_epc_phase0') {
    echo "FAIL database\n";
    exit(1);
}
$passed = 0;
$failed = 0;
function p5(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$sql = (string) file_get_contents($root . '/migrations/296_auto_ref_us_sources.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}
$companyColumn = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_auto_ref_us_epa_vehicles' AND COLUMN_NAME = 'company_id'")->fetchColumn();
p5($companyColumn === 0, 'US reference tables have no company_id');
$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/automotive.php');
p5(!preg_match('/post\(\$autoApp\(\'us-catalog/', $routes), 'US catalog has no write route, so there is no CSRF write to bypass');

$dir = getenv('TEMP') . '/rateb-epc-staging/us-2026-11-04';
$importer = new AutomotiveUsImportService($pdo);
$openBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn();
$inventoryBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$dry = $importer->dryRun($dir);
p5($dry['nhtsa_makes'] === 12417 && $dry['nhtsa_models'] === 32430 && $dry['epa_vehicles'] > 40000 && $dry['nhtsa_manufacturers'] > 1000, 'dry-run counts match the local snapshots');
$importer->import($dir, '2026-11-04');
$duplicate = false;
try {
    $importer->import($dir, '2026-11-04');
} catch (RuntimeException $e) {
    $duplicate = $e->getMessage() === 'duplicate_release';
}
p5($duplicate, 'importing the same file hash again is rejected');
p5((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_us_releases')->fetchColumn() === 4, 'four source releases were stored');
p5((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_us_nhtsa_makes')->fetchColumn() === 12417, 'NHTSA makes imported once');
p5((int) $pdo->query("SELECT COUNT(*) FROM rateb_auto_ref_us_releases WHERE market_scope = 'US'")->fetchColumn() === 4, 'market scope stays US');
p5((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn() === $openBefore, 'VehiclesDB models were not modified');
p5((int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn() === $inventoryBefore && (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn() === $supplierBefore, 'inventory and suppliers were not modified');

$allow = false;
$denied = false;
try {
    (new AutomotiveUsCatalogService($pdo))->epa(0, '', '', '', '', 1);
} catch (RuntimeException) {
    $denied = true;
}
p5($denied, 'US catalog read is denied without permission');
$allow = true;
$service = new AutomotiveUsCatalogService($pdo);
$page = $service->epa(0, '', '', '', '', 1);
$huge = $service->epa(0, '', '', '', '', PHP_INT_MAX);
p5(count($page['rows']) === 25 && $huge['page'] > 1 && count($huge['rows']) <= 25, 'EPA pages stay bounded');
$sample = $page['rows'][0];
TenantContext::setCompanyId(70);
$one = $service->epaDetail((int) $sample['id']);
TenantContext::setCompanyId(71);
$two = $service->epaDetail((int) $sample['id']);
p5($one['epa_id'] === $two['epa_id'] && $one['market_scope'] === 'US' && str_contains((string) $one['license_evidence'], 'public domain'), 'tenant context cannot change a global EPA row');
$fuel = $service->epa((int) $sample['model_year'], (string) $sample['make_name'], '', (string) $sample['fuel_type1'], '', 1);
p5($fuel['total'] > 0, 'year, make, and fuel filters return rows');
$nhtsaMake = (string) $pdo->query('SELECT make_name FROM rateb_auto_ref_us_nhtsa_models ORDER BY id LIMIT 1')->fetchColumn();
$nhtsa = $service->nhtsaModels($nhtsaMake, '', 1);
p5($nhtsa['total'] > 0 && $nhtsa['rows'][0]['make_name'] === $nhtsaMake, 'NHTSA model search finds the official make without inventing a model year');
$view = (string) file_get_contents(dirname(__DIR__) . '/views/us-catalog/show.php');
p5(str_contains($view, 'dir="') && str_contains($view, 'economyUnit') && str_contains((string) file_get_contents(dirname(__DIR__) . '/config/lang/ar.php'), 'بيانات السوق الأمريكي فقط'), 'detail view keeps source units and Arabic labels');

echo "Phase 5 checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
